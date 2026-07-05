<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\PaymentMode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Shared validation for collecting a payment against an obligation
 * (mirrors App\Http\Requests\CollectPaymentRequest).
 */
trait CollectsPayments
{
    protected function validateCollection(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'payment_type' => ['required', Rule::in(array_keys(config('hsms.payment_types')))],
            'mode' => ['required', Rule::in(PaymentMode::active()->pluck('code')->all())],
            'reference_number' => [
                Rule::requiredIf(fn () => (bool) optional(
                    PaymentMode::active()->where('code', $request->mode)->first()
                )->requires_reference),
                'nullable', 'string', 'max:100',
            ],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'reference_number.required' => 'A reference number is required for this payment mode.',
        ]);
    }
}
