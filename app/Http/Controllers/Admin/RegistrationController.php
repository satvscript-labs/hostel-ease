<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(): View
    {
        $hostel = Hostel::findOrFail(Tenant::id());
        $token = $hostel->ensureRegistrationToken();
        $url = url('register/'.$token);

        $qr = null;
        try {
            $qr = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(180)->margin(0)->generate($url);
        } catch (\Throwable $e) {
            $qr = null;
        }

        $pending = StudentRegistration::pending()->orderBy('created_at')->get();

        return view('admin.registrations.index', compact('url', 'qr', 'pending'));
    }

    public function regenerate(): RedirectResponse
    {
        $hostel = Hostel::findOrFail(Tenant::id());
        $hostel->forceFill(['registration_token' => Str::random(24)])->save();

        return back()->with('success', 'New registration link generated. The old link no longer works.');
    }

    public function approve(StudentRegistration $registration): RedirectResponse
    {
        abort_unless($registration->status === 'pending' && $registration->hostel_id === Tenant::id(), 404);

        $student = Student::create([
            'hostel_id' => $registration->hostel_id,
            'name' => $registration->name,
            'mobile' => $registration->mobile,
            'father_mobile' => $registration->father_mobile,
            'mother_mobile' => $registration->mother_mobile,
            'aadhaar' => $registration->aadhaar,
            'address' => $registration->address,
            'city' => $registration->city,
            'state' => $registration->state,
            'occupation_type' => $registration->occupation_type,
            'join_date' => $registration->joining_date,
            'photo' => $registration->photo,
            'status' => 'active',
        ]);

        $registration->update(['status' => 'approved', 'student_id' => $student->id, 'reviewed_at' => now()]);
        $this->logger->log('registration.approve', "Approved {$registration->name}", $student);

        return redirect()->route('admin.students.show', $student)->with('success', 'Registration approved — student created.');
    }

    public function reject(StudentRegistration $registration): RedirectResponse
    {
        abort_unless($registration->status === 'pending' && $registration->hostel_id === Tenant::id(), 404);
        $registration->update(['status' => 'rejected', 'reviewed_at' => now()]);

        return back()->with('success', 'Registration rejected.');
    }
}
