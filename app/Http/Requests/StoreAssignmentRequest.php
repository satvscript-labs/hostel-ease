<?php

namespace App\Http\Requests;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHostelAdmin() ?? false;
    }

    public function rules(): array
    {
        $tenant = Tenant::id();

        return [
            'student_id' => ['required', Rule::exists('students', 'id')->where('hostel_id', $tenant)->whereNull('deleted_at')],
            'bed_id' => ['required', Rule::exists('beds', 'id')->where('hostel_id', $tenant)->whereNull('deleted_at')],
            'join_date' => ['required', 'date'],
            'fee_amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'fee_frequency' => ['required', Rule::in(array_keys(config('hostelease.fee_frequencies')))],
            'semester' => ['nullable', 'integer', Rule::in(config('hostelease.semesters'))],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}

