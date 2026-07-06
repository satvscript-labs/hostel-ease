<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHostelAdmin() ?? false;
    }

    /**
     * Normalise mobile + aadhaar inputs. Phones get +91 prefix; aadhaar gets all 12 digits.
     */
    protected function prepareForValidation(): void
    {
        $normalize = fn ($v) => $v === null ? null : '+91' . substr(preg_replace('/\D+/', '', $v), -10);
        $aadhaar = fn ($v) => $v === null ? null : substr(preg_replace('/\D+/', '', $v), -12);

        $this->merge([
            'mobile' => $normalize($this->mobile),
            'father_mobile' => $normalize($this->father_mobile),
            'mother_mobile' => $normalize($this->mother_mobile),
            'guardian_mobile' => $normalize($this->guardian_mobile),
            'aadhaar' => $aadhaar($this->aadhaar),
        ]);
    }

    public function rules(): array
    {
        $mobile = ['nullable', 'regex:/^\+91\d{10}$/'];

        return [
            'name' => ['required', 'string', 'max:150'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'mobile' => ['required', 'regex:/^\+91\d{10}$/'],
            'father_mobile' => ['required', 'regex:/^\+91\d{10}$/'],
            'mother_mobile' => $mobile,
            'guardian_mobile' => $mobile,
            'aadhaar' => ['required', 'digits:12'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'occupation_type' => ['required', Rule::in(array_keys(config('hostelease.occupation_types')))],
            'join_date' => ['required', 'date'],
            'leave_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'status' => ['required', Rule::in(['active', 'left'])],
        ];
    }
}

