<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentMode;
use App\Models\SecurityDeposit;
use App\Models\StaffSalaryPayment;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Payment modes are LOAD-BEARING for four modules (W6.1–W6.3): collections,
 * expenses, staff salaries and security deposits all validate against
 * active() modes, and historical rows carry the mode code for their labels.
 * The guards here exist so this page can never brick those forms (no active
 * modes left → Rule::in([]) rejects everything) or orphan history (deleting
 * a referenced mode's labels).
 */
class PaymentModeController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(): View
    {
        $modes = PaymentMode::ordered()->get();

        // "In use" per mode across everything that references a mode — shown
        // on the card, and it's what decides delete vs deactivate.
        $usage = $this->usageCounts($modes);

        return view('admin.payment_modes.index', compact('modes', 'usage'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        PaymentMode::create([
            'name' => $data['name'],
            'code' => $this->uniqueCode($data['name']),
            'requires_reference' => $request->boolean('requires_reference'),
            'is_active' => true,
            'sort_order' => (int) PaymentMode::max('sort_order') + 1,
        ]);

        $this->logger->log('payment_mode.create', "Added payment mode {$data['name']}");

        return back()->with('success', 'Payment mode added.');
    }

    public function update(Request $request, PaymentMode $payment_mode): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $goingInactive = ! $request->boolean('is_active') && $payment_mode->is_active;

        // At least one active mode, always — every money form in the app
        // (collect, expense, salary, deposit) validates against active modes;
        // zero active modes means nobody can record money at all.
        if ($goingInactive && ! PaymentMode::active()->whereKeyNot($payment_mode->id)->exists()) {
            return back()->with('error', 'This is the last active payment mode — every money form needs at least one. Add or activate another before deactivating this.');
        }

        $payment_mode->update([
            'name' => $data['name'],
            'requires_reference' => $request->boolean('requires_reference'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logger->log('payment_mode.update', "Updated payment mode {$payment_mode->name}", $payment_mode);

        return back()->with('success', 'Payment mode updated.');
    }

    public function destroy(PaymentMode $payment_mode): RedirectResponse
    {
        // Referenced modes deactivate, never delete (owner decision, W6.4):
        // historical payments/expenses/salaries name their mode by code, and
        // deleting the mode would orphan every one of those labels.
        $used = $this->usageCounts(collect([$payment_mode]))[$payment_mode->id] ?? 0;
        if ($used > 0) {
            return back()->with('error', "\"{$payment_mode->name}\" is on {$used} recorded transaction(s) — history keeps its label. Deactivate it instead; it disappears from every form but old records stay readable.");
        }

        if ($payment_mode->is_active && ! PaymentMode::active()->whereKeyNot($payment_mode->id)->exists()) {
            return back()->with('error', 'This is the last active payment mode — every money form needs at least one. Add another before removing this.');
        }

        $payment_mode->delete();
        $this->logger->log('payment_mode.delete', "Deleted payment mode {$payment_mode->name}");

        return back()->with('success', 'Payment mode removed.');
    }

    /** @return array<int, int> mode id => total references across the four modules */
    protected function usageCounts($modes): array
    {
        $codes = $modes->pluck('code');

        $byCode = fn ($query) => $query->whereIn('mode', $codes)
            ->selectRaw('mode, COUNT(*) as c')->groupBy('mode')->pluck('c', 'mode');

        $payments = $byCode(Payment::query());
        $expenses = $byCode(Expense::query());
        $salaries = $byCode(StaffSalaryPayment::query());
        $deposits = SecurityDeposit::whereIn('payment_mode_id', $modes->pluck('id'))
            ->selectRaw('payment_mode_id, COUNT(*) as c')->groupBy('payment_mode_id')->pluck('c', 'payment_mode_id');

        return $modes->mapWithKeys(fn (PaymentMode $m) => [$m->id => (int) ($payments[$m->code] ?? 0)
            + (int) ($expenses[$m->code] ?? 0)
            + (int) ($salaries[$m->code] ?? 0)
            + (int) ($deposits[$m->id] ?? 0),
        ])->all();
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'requires_reference' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    protected function uniqueCode(string $name): string
    {
        $base = PaymentMode::makeCode($name);
        $code = $base;
        $i = 2;
        while (PaymentMode::where('code', $code)->exists()) {
            $code = $base.'_'.$i++;
        }

        return $code;
    }
}
