<?php

namespace App\Http\Controllers;

use App\Models\Hostel;
use App\Models\StudentRegistration;
use App\Services\ImageService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public (unauthenticated) student self-registration form, reached via a
 * per-hostel token/QR link. Submissions land in a pending list for the admin
 * to approve.
 */
class PublicRegistrationController extends Controller
{
    public function __construct(
        protected ImageService $imageService,
        protected StorageService $storageService
    ) {}
    public function show(string $token)
    {
        $hostel = Hostel::where('registration_token', $token)->firstOrFail();

        return view('public.register', ['hostel' => $hostel, 'token' => $token]);
    }

    public function submit(Request $request, string $token)
    {
        $hostel = Hostel::where('registration_token', $token)->firstOrFail();

        $digits = fn ($v) => $v === null ? null : substr(preg_replace('/\D+/', '', $v), -10);
        $request->merge([
            'mobile' => $digits($request->mobile),
            'father_mobile' => $digits($request->father_mobile),
            'mother_mobile' => $digits($request->mother_mobile),
            'aadhaar' => $request->aadhaar ? preg_replace('/\D+/', '', $request->aadhaar) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'digits:10'],
            'father_mobile' => ['required', 'digits:10'],
            'mother_mobile' => ['nullable', 'digits:10'],
            'aadhaar' => ['required', 'digits:12'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'occupation_type' => ['required', Rule::in(array_keys(config('hostelease.occupation_types')))],
            'college' => ['nullable', 'required_if:occupation_type,student', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'required_if:occupation_type,student', 'string', 'max:255'],
            'joining_date' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'aadhaar_file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if ($request->hasFile('photo')) {
            $processed = $this->imageService->compressAndConvertToWebp($request->file('photo'), 800, 800, 80);
            $data['photo'] = $this->storageService->store($processed['content'], 'registrations/photos', 'public', $processed['extension']);
        }

        StudentRegistration::create($data + ['hostel_id' => $hostel->id, 'status' => 'pending']);

        return view('public.register', ['hostel' => $hostel, 'token' => $token, 'submitted' => true]);
    }
}

