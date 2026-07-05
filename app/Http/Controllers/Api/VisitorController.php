<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VisitorController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $visitors = Visitor::with('student')
            ->when($request->input('filter') === 'inside', fn ($q) => $q->inside())
            ->when($request->filled('date'), fn ($q) => $q->whereDate('check_in', $request->date('date')))
            ->orderByDesc('check_in')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'mobile' => $v->mobile,
                'purpose' => $v->purpose,
                'id_proof' => $v->id_proof,
                'student' => $v->student?->name,
                'check_in' => $v->check_in?->toIso8601String(),
                'check_out' => $v->check_out?->toIso8601String(),
                'inside' => $v->isInside(),
            ]);

        return response()->json([
            'visitors' => $visitors,
            'inside_count' => Visitor::inside()->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'student_id' => ['nullable', Rule::exists('students', 'id')->where('hostel_id', Tenant::id())],
            'purpose' => ['nullable', 'string', 'max:150'],
            'id_proof' => ['nullable', 'string', 'max:100'],
            'check_in' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['check_in'] = $data['check_in'] ?? now();
        $visitor = Visitor::create($data);
        $this->logger->log('visitor.checkin', "Visitor {$visitor->name} checked in", $visitor);

        return response()->json(['message' => 'Visitor checked in.', 'id' => $visitor->id], 201);
    }

    public function checkout(int $visitor): JsonResponse
    {
        $model = Visitor::findOrFail($visitor);
        if ($model->isInside()) {
            $model->update(['check_out' => now()]);
            $this->logger->log('visitor.checkout', "Visitor {$model->name} checked out", $model);
        }

        return response()->json(['message' => 'Visitor checked out.']);
    }

    public function destroy(int $visitor): JsonResponse
    {
        Visitor::findOrFail($visitor)->delete();

        return response()->json(['message' => 'Visitor record removed.']);
    }
}
