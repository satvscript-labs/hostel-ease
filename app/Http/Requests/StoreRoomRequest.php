<?php

namespace App\Http\Requests;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHostelAdmin() ?? false;
    }

    public function rules(): array
    {
        $roomId = $this->route('room')?->id;

        return [
            'floor_id' => ['required', Rule::exists('floors', 'id')->where('hostel_id', Tenant::id())],
            'room_number' => [
                'required', 'string', 'max:50',
                Rule::unique('rooms', 'room_number')
                    ->where('hostel_id', Tenant::id())
                    ->whereNull('deleted_at')
                    ->ignore($roomId),
            ],
            'room_type' => ['required', Rule::in(array_keys(config('hostelease.room_types')))],
            'sharing' => ['required', 'integer', Rule::in(config('hostelease.sharing_options'))],
        ];
    }

    public function messages(): array
    {
        return [
            'room_number.unique' => 'A room with this number already exists in your hostel.',
        ];
    }
}

