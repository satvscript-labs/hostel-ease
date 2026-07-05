<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Student;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(Request $request): View
    {
        $complaints = Complaint::with('student', 'creator')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->get();

        $counts = [
            'open' => Complaint::where('status', 'open')->count(),
            'in_progress' => Complaint::where('status', 'in_progress')->count(),
            'resolved' => Complaint::where('status', 'resolved')->count(),
        ];

        $students = Student::active()->orderBy('name')->get(['id', 'name']);

        return view('admin.complaints.index', compact('complaints', 'counts', 'students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['nullable', Rule::exists('students', 'id')->where('hostel_id', \App\Support\Tenant::id())],
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(config('hostelease.complaint_categories')))],
            'priority' => ['required', Rule::in(array_keys(config('hostelease.complaint_priorities')))],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $complaint = Complaint::create($data + ['status' => 'open', 'created_by' => Auth::id()]);
        $this->logger->log('complaint.create', "Complaint: {$complaint->title}", $complaint);

        return back()->with('success', 'Complaint logged.');
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('hostelease.complaint_statuses')))],
            'resolution' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['resolved_at'] = in_array($data['status'], ['resolved', 'closed'], true) ? now() : null;
        $complaint->update($data);
        $this->logger->log('complaint.update', "Complaint #{$complaint->id} → {$data['status']}", $complaint);

        return back()->with('success', 'Complaint updated.');
    }

    public function destroy(Complaint $complaint): RedirectResponse
    {
        $complaint->delete();

        return back()->with('success', 'Complaint removed.');
    }
}

