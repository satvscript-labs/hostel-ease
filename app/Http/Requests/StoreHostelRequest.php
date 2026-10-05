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
            // Store in the same +91XXXXXXXXXX form as every other login (owners,
            // staff, students — see LoginController). Bare 10-digit numbers here
            // meant a new branch never matched its existing owner (a duplicate
            // customer was created) and the auto-created owner login could never
            // sign in, since login normalises the entered number to +91 form.
            $this->merge(['mobile' => '+91'.substr(preg_replace('/\D+/', '', $this->mobile), -10)]);
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
            'mobile' => ['required', 'regex:/^\+91\d{10}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(array_keys(config('hostelease.hostel_status')))],
            // NO coverage dates (doc 22): only the billing service writes coverage, and
            // a date typed here bypassed it — it now follows the account's renewal date.
            // Creation only
            'plan' => [$this->isMethod('post') ? 'required' : 'nullable', Rule::in(['yearly', 'monthly', 'trial'])],
            // May only LOWER the server's quote (enforced in the billing service too);
            // bounded like every other money input.
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            // How the first charge is collected: recorded as received, or a payment link.
            // (Replaced a paid / pending / failed status — "failed" meant nothing here.)
            'collect' => ['nullable', Rule::in(['offline', 'link'])],
            // No 'comp': complimentary coverage has its own audited path (Account 360 → Comp).
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online'])],
            'transaction_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'mobile.unique' => 'This mobile number is already used by another login.',
        ];
    }
}

