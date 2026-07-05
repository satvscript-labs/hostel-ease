<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    /** The shareable self-registration link + token for the active hostel. */
    public function link(): JsonResponse
    {
        $hostel = Hostel::findOrFail(Tenant::id());
        $token = $hostel->ensureRegistrationToken();

        return response()->json([
            'token' => $token,
            'url' => url('register/'.$token),
            'pending_count' => StudentRegistration::pending()->count(),
        ]);
    }

    public function regenerate(): JsonResponse
    {
        $hostel = Hostel::findOrFail(Tenant::id());
        $hostel->forceFill(['registration_token' => Str::random(24)])->save();

        return response()->json([
            'message' => 'New link generated. The old link no longer works.',
            'token' => $hostel->registration_token,
            'url' => url('register/'.$hostel->registration_token),
        ]);
    }

    public function index(): JsonResponse
    {
        $pending = StudentRegistration::pending()->orderBy('created_at')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'mobile' => $r->mobile,
            'father_mobile' => $r->father_mobile,
            'mother_mobile' => $r->mother_mobile,
            'aadhaar' => $r->aadhaar,
            'occupation_type' => $r->occupation_type,
            'address' => $r->address,
            'city' => $r->city,
            'state' => $r->state,
            'photo_url' => $r->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($r->photo) : null,
            'submitted_at' => $r->created_at?->toIso8601String(),
        ]);

        return response()->json(['registrations' => $pending]);
    }

    public function approve(int $registration): JsonResponse
    {
        $reg = StudentRegistration::where('status', 'pending')->findOrFail($registration);

        $student = Student::create([
            'hostel_id' => $reg->hostel_id,
            'name' => $reg->name,
            'mobile' => $reg->mobile,
            'father_mobile' => $reg->father_mobile,
            'mother_mobile' => $reg->mother_mobile,
            'aadhaar' => $reg->aadhaar,
            'address' => $reg->address,
            'city' => $reg->city,
            'state' => $reg->state,
            'occupation_type' => $reg->occupation_type,
            'photo' => $reg->photo,
            'status' => 'active',
        ]);

        $reg->update(['status' => 'approved', 'student_id' => $student->id, 'reviewed_at' => now()]);
        $this->logger->log('registration.approve', "Approved registration {$reg->name}", $student);

        return response()->json(['message' => 'Registration approved — student created.', 'student_id' => $student->id]);
    }

    public function reject(int $registration): JsonResponse
    {
        $reg = StudentRegistration::where('status', 'pending')->findOrFail($registration);
        $reg->update(['status' => 'rejected', 'reviewed_at' => now()]);

        return response()->json(['message' => 'Registration rejected.']);
    }
}
