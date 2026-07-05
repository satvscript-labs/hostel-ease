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
            'mode' => ['required', Rule::in(array_keys(config('hostelease.payment_modes')))],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

