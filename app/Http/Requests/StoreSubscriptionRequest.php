<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'hostel_id' => ['required', 'exists:hostels,id'],
            'plan' => ['nullable', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', Rule::in(['paid', 'pending', 'failed'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online'])],
            'transaction_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
