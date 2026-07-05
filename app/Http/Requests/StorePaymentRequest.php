<?php

namespace App\Http\Requests;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHostelAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', Rule::exists('students', 'id')->where('hostel_id', Tenant::id())->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'payment_type' => ['required', Rule::in(array_keys(config('hsms.payment_types')))],
            'mode' => ['required', Rule::in(\App\Models\PaymentMode::active()->pluck('code')->all())],
            // Modes flagged as requiring a reference must carry one.
            'reference_number' => [
                Rule::requiredIf(fn () => (bool) optional(
                    \App\Models\PaymentMode::active()->where('code', $this->mode)->first()
                )->requires_reference),
                'nullable', 'string', 'max:100',
            ],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:500'],
            // Optional obligation this payment settles (type:id). Resolved in the controller.
            'payable' => ['nullable', 'string', 'regex:/^(semester_fee|monthly_rent|ac_bill_student):\d+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference_number.required' => 'A reference number is required for this payment mode.',
        ];
    }
}
