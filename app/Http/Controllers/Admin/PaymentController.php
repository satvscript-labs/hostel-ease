<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Mail\ReceiptMail;
use App\Models\AcBillStudent;
use App\Models\MonthlyRent;
use App\Models\Payment;
use App\Models\SemesterFee;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use App\Services\WhatsAppService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
        protected WhatsAppService $whatsapp,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): View
    {
        $payments = Payment::with('student', 'collector')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('paid_on', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('paid_on', '<=', $request->date('to')))
            ->when($request->filled('mode'), fn ($q) => $q->where('mode', $request->mode))
            ->orderByDesc('paid_on')->orderByDesc('id')
            ->get();

        $total = $payments->sum('amount');

        return view('admin.payments.index', compact('payments', 'total'));
    }

    public function create(Request $request): View
    {
        // Load each active student with their unpaid obligations so the form can
        // offer a "Pay towards" selector — a payment tied to a due settles it.
        $students = Student::active()
            ->with([
                'semesterFees' => fn ($q) => $q->where('status', '!=', 'paid')->orderBy('semester'),
                'monthlyRents' => fn ($q) => $q->where('status', '!=', 'paid')->orderBy('rent_month'),
                'acBillShares' => fn ($q) => $q->where('status', '!=', 'paid')->with('acBill'),
            ])
            ->orderBy('name')
            ->get();

        $selected = $request->integer('student') ?: null;

        // Map of student_id → list of payable dues, for the dynamic selector.
        $duesMap = [];
        foreach ($students as $s) {
            $list = [];
            foreach ($s->semesterFees as $f) {
                $list[] = ['ref' => "semester_fee:{$f->id}", 'balance' => (float) $f->balance,
                    'label' => "Semester {$f->semester} — due ".hsms_money($f->balance)];
            }
            foreach ($s->monthlyRents as $r) {
                $list[] = ['ref' => "monthly_rent:{$r->id}", 'balance' => (float) $r->balance,
                    'label' => 'Rent '.optional($r->rent_month)->format('M Y').' — due '.hsms_money($r->balance)];
            }
            foreach ($s->acBillShares as $a) {
                $bal = max(0, (float) $a->amount - (float) $a->paid_amount);
                $list[] = ['ref' => "ac_bill_student:{$a->id}", 'balance' => $bal,
                    'label' => 'AC Bill '.optional(optional($a->acBill)->bill_month)->format('M Y').' — due '.hsms_money($bal)];
            }
            if ($list) {
                $duesMap[$s->id] = $list;
            }
        }

        // Optional deep-link from a student profile: pre-select a specific due.
        $preRef = $request->filled('payable_type') && $request->filled('payable_id')
            ? $request->input('payable_type').':'.$request->integer('payable_id')
            : null;

        return view('admin.payments.create', compact('students', 'selected', 'duesMap', 'preRef'));
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $payable = $this->resolvePayable($request->input('payable'), (int) $data['student_id']);

        $payment = $this->payments->record($data, $payable);

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', "Payment recorded — receipt {$payment->receipt_number}.");
    }

    /**
     * Resolve a "type:id" payable reference to its model, scoped to the paying
     * student (and the active tenant via the global scope). Returns null for a
     * general/advance payment not tied to any due.
     */
    protected function resolvePayable(?string $ref, int $studentId): ?Model
    {
        if (! $ref || ! str_contains($ref, ':')) {
            return null;
        }

        [$type, $id] = explode(':', $ref, 2);

        return match ($type) {
            'semester_fee' => SemesterFee::where('student_id', $studentId)->find($id),
            'monthly_rent' => MonthlyRent::where('student_id', $studentId)->find($id),
            'ac_bill_student' => AcBillStudent::where('student_id', $studentId)->find($id),
            default => null,
        };
    }

    public function show(Payment $payment): View
    {
        $payment->load('student', 'collector', 'hostel');

        return view('admin.payments.show', compact('payment'));
    }

    public function pdf(Payment $payment): Response
    {
        $payment->load('student', 'collector', 'hostel');
        $pdf = Pdf::loadView('admin.payments.receipt_pdf', ['payment' => $payment]);

        return $pdf->download($payment->receipt_number.'.pdf');
    }

    public function whatsapp(Payment $payment): RedirectResponse
    {
        $payment->load('student', 'hostel');
        $message = $this->receiptMessage($payment);

        // If the Cloud API is configured, send directly; otherwise hand the admin a wa.me link.
        if ($this->whatsapp->enabled()) {
            $this->whatsapp->send($payment->student->mobile, $message);

            return back()->with('success', 'Receipt sent on WhatsApp.');
        }

        return redirect()->away($this->whatsapp->link($payment->student->mobile, $message));
    }

    public function email(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $payment->load('student', 'collector', 'hostel');
        $pdf = Pdf::loadView('admin.payments.receipt_pdf', ['payment' => $payment])->output();

        Mail::to($data['email'])->queue(new ReceiptMail($payment, $pdf));
        $this->logger->log('payment.email', "Emailed receipt {$payment->receipt_number} to {$data['email']}", $payment);

        return back()->with('success', "Receipt emailed to {$data['email']}.");
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        // Reverse against the linked obligation so the student's balance is restored.
        $this->payments->reverse($payment);

        return redirect()->route('admin.payments.index')->with('success', 'Payment removed and balance restored.');
    }

    protected function receiptMessage(Payment $payment): string
    {
        return sprintf(
            "Dear %s,\nWe have received %s towards your hostel fees at %s.\nReceipt No: %s\nDate: %s\nThank you!",
            $payment->student->name,
            hsms_money($payment->amount),
            $payment->hostel->name,
            $payment->receipt_number,
            $payment->paid_on->format('d M Y'),
        );
    }
}
