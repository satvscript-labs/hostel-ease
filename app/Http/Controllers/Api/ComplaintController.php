<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Complaint management for the mobile app (tenant-scoped).
 */
class ComplaintController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $complaints = Complaint::with('student')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => $this->present($c));

        return response()->json([
            'complaints' => $complaints,
            'counts' => [
                'open' => Complaint::where('status', 'open')->count(),
                'in_progress' => Complaint::where('status', 'in_progress')->count(),
                'resolved' => Complaint::where('status', 'resolved')->count(),
            ],
            'options' => [
                'categories' => config('hostelease.complaint_categories'),
                'priorities' => config('hostelease.complaint_priorities'),
                'statuses' => config('hostelease.complaint_statuses'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['nullable', Rule::exists('students', 'id')->where('hostel_id', Tenant::id())],
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(config('hostelease.complaint_categories')))],
            'priority' => ['required', Rule::in(array_keys(config('hostelease.complaint_priorities')))],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $complaint = Complaint::create($data + ['status' => 'open', 'created_by' => $request->user()->id]);
        $this->logger->log('complaint.create', "Complaint: {$complaint->title}", $complaint);

        return response()->json([
            'message' => 'Complaint logged.',
            'complaint' => $this->present($complaint->load('student')),
        ], 201);
    }

    public function update(Request $request, int $complaint): JsonResponse
    {
        // Explicit resolve so TenantScope guarantees the complaint is in the active branch.
        $complaint = Complaint::findOrFail($complaint);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('hostelease.complaint_statuses')))],
            'resolution' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['resolved_at'] = in_array($data['status'], ['resolved', 'closed'], true) ? now() : null;
        $complaint->update($data);
        $this->logger->log('complaint.update', "Complaint #{$complaint->id} → {$data['status']}", $complaint);

        return response()->json([
            'message' => 'Complaint updated.',
            'complaint' => $this->present($complaint->load('student')),
        ]);
    }

    protected function present(Complaint $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'category' => $c->category,
            'priority' => $c->priority,
            'status' => $c->status,
            'description' => $c->description,
            'resolution' => $c->resolution,
            'student' => $c->student?->name,
            'student_id' => $c->student_id,
            'created_at' => $c->created_at?->toIso8601String(),
            'resolved_at' => $c->resolved_at?->toIso8601String(),
        ];
    }
}

