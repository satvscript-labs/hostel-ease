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
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'credit_used' => ['nullable', 'numeric', 'min:0'],
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

    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator) {
                $amount = (float) $this->input('amount', 0);
                $creditUsed = (float) $this->input('credit_used', 0);

                if ($amount + $creditUsed <= 0) {
                    $validator->errors()->add('amount', 'The total payment amount (cash + credit) must be greater than zero.');
                }

                if ($creditUsed > 0 && $this->filled('student_id')) {
                    $student = \App\Models\Student::find($this->input('student_id'));
                    if ($student && $creditUsed > (float) $student->credit_balance) {
                        $validator->errors()->add('credit_used', 'Cannot use more credit than the student has available (₹' . number_format($student->credit_balance, 2) . ').');
                    }
                }
            }
        ];
    }

    public function messages(): array
    {
        return [
            'reference_number.required' => 'A reference number is required for this payment mode.',
        ];
    }
}

