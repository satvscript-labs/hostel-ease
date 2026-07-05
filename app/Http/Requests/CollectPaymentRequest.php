<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a payment collected against an existing obligation
 * (semester fee / monthly rent / AC bill). The student is derived from the
 * obligation, so only the money fields are validated here.
 */
class CollectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHostelAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'payment_type' => ['required', Rule::in(array_keys(config('hsms.payment_types')))],
            'mode' => ['required', Rule::in(\App\Models\PaymentMode::active()->pluck('code')->all())],
            'reference_number' => [
                Rule::requiredIf(fn () => (bool) optional(
                    \App\Models\PaymentMode::active()->where('code', $this->mode)->first()
                )->requires_reference),
                'nullable', 'string', 'max:100',
            ],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference_number.required' => 'A reference number is required for this payment mode.',
        ];
    }
}
