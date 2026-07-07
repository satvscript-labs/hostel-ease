<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Visitor;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VisitorController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }



    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['nullable', 'regex:/^(\+91)?\d{10}$/'],
            'student_id' => ['nullable', Rule::exists('students', 'id')->where('hostel_id', \App\Support\Tenant::id())],
            'purpose' => ['nullable', 'string', 'max:150'],
            'id_proof' => ['nullable', 'string', 'max:100'],
            'check_in' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Normalize mobile to +91 format
        if (!blank($data['mobile'])) {
            $digits = substr(preg_replace('/\D+/', '', $data['mobile']), -10);
            $data['mobile'] = '+91' . $digits;
        }

        $data['check_in'] = $data['check_in'] ?? now();
        $visitor = Visitor::create($data);
        $this->logger->log('visitor.checkin', "Visitor {$visitor->name} checked in", $visitor);

        return back()->with('success', 'Visitor checked in.');
    }

    public function checkout(Visitor $visitor): RedirectResponse
    {
        if ($visitor->isInside()) {
            $visitor->update(['check_out' => now()]);
            $this->logger->log('visitor.checkout', "Visitor {$visitor->name} checked out", $visitor);
        }

        return back()->with('success', 'Visitor checked out.');
    }

    public function destroy(Visitor $visitor): RedirectResponse
    {
        $visitor->delete();

        return back()->with('success', 'Visitor record removed.');
    }
}
