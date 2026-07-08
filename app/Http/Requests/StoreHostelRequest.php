<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHostelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->mobile) {
            $this->merge(['mobile' => substr(preg_replace('/\D+/', '', $this->mobile), -10)]);
        }
    }

    public function rules(): array
    {
        // The hostel mobile doubles as the admin login. A mobile that already
        // belongs to a hostel admin is allowed — the new hostel is linked to
        // that same owner as an additional branch (see HostelService::provision).
        return [
            'name' => ['required', 'string', 'max:150'],
            'owner_name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'digits:10'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(array_keys(config('hostelease.hostel_status')))],
            // Update only
            'subscription_start' => [$this->isMethod('put') ? 'required' : 'nullable', 'date'],
            'subscription_end' => [$this->isMethod('put') ? 'required' : 'nullable', 'date', 'after:subscription_start'],
            // Creation only
            'plan' => [$this->isMethod('post') ? 'required' : 'nullable', Rule::in(['yearly', 'monthly', 'trial'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['nullable', Rule::in(['paid', 'pending', 'failed'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online', 'comp'])],
            'transaction_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.unique' => 'This mobile number is already used by another login.',
        ];
    }
}

