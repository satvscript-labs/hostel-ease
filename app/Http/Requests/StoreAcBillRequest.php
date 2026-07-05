<?php

namespace App\Http\Requests;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHostelAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', Rule::exists('rooms', 'id')->where('hostel_id', Tenant::id())->where('room_type', 'ac')],
            'bill_month' => ['required', 'date_format:Y-m'],
            'previous_unit' => ['required', 'numeric', 'min:0'],
            'current_unit' => ['required', 'numeric', 'gte:previous_unit'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:1000'],
            'distribution' => ['required', Rule::in(['equal', 'selected'])],
            'students' => ['array'],
            'students.*' => ['integer'],
            'selected_students' => [Rule::requiredIf(fn () => $this->distribution === 'selected')],
        ];
    }

    public function messages(): array
    {
        return [
            'current_unit.gte' => 'Current reading must be greater than or equal to the previous reading.',
            'room_id.exists' => 'Select a valid AC room.',
            'selected_students.required' => 'Choose at least one student for selected distribution.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Mirror the students array into a scalar for the "required when selected" check.
        $this->merge([
            'selected_students' => count($this->input('students', [])) ? '1' : null,
        ]);
    }
}
