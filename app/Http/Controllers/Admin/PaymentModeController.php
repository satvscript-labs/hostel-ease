<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMode;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentModeController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(): View
    {
        $modes = PaymentMode::ordered()->get();

        return view('admin.payment_modes.index', compact('modes'));
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
        $payment_mode->delete();
        $this->logger->log('payment_mode.delete', "Deleted payment mode {$payment_mode->name}");

        return back()->with('success', 'Payment mode removed.');
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
