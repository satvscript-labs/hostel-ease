<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReceiptMail;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use App\Services\WhatsAppService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
        protected WhatsAppService $whatsapp,
        protected ActivityLogger $logger,
    ) {
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
        // Reverse against the linked invoice(s) so the student's balance is restored.
        $this->payments->reverse($payment);

        return redirect()->route('admin.finance.index')->with('success', 'Payment removed and balance restored.');
    }

    protected function receiptMessage(Payment $payment): string
    {
        return sprintf(
            "Dear %s,\nWe have received %s towards your hostel fees at %s.\nReceipt No: %s\nDate: %s\nThank you!",
            $payment->student->name,
            hostelease_money($payment->amount),
            $payment->hostel->name,
            $payment->receipt_number,
            $payment->paid_on->format('d M Y'),
        );
    }
}
