<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHostelAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(config('hostelease.expense_categories')))],
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'paid_to' => ['nullable', 'string', 'max:150'],
            // W6.2: validated against the tenant's payment_modes table (like
            // student payments since W6.1), not the hardcoded config list — a
            // hostel that adds "PhonePe" can spend through it too, and a
            // deactivated mode stops being spendable everywhere at once.
            'mode' => ['required', Rule::in(\App\Models\PaymentMode::active()->pluck('code')->all())],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

