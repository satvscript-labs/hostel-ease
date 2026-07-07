<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Student;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FrontDeskController extends Controller
{
    public function index(Request $request): View
    {
        // Visitors data
        $visitors = Visitor::with('student')
            ->when($request->input('filter') === 'inside', fn ($q) => $q->inside())
            ->when($request->filled('date'), fn ($q) => $q->whereDate('check_in', $request->date('date')))
            ->orderByRaw('check_out IS NOT NULL')
            ->orderByDesc('updated_at')
            ->get();
        $insideCount = Visitor::inside()->count();

        // Complaints data
        $complaints = Complaint::with('student', 'creator')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->get();
            
        $complaintCounts = [
            'open' => Complaint::where('status', 'open')->count(),
            'in_progress' => Complaint::where('status', 'in_progress')->count(),
            'resolved' => Complaint::where('status', 'resolved')->count(),
        ];

        // Shared dropdown data
        $students = Student::active()->orderBy('name')->get(['id', 'name']);

        return view('admin.frontdesk.index', compact('visitors', 'insideCount', 'complaints', 'complaintCounts', 'students'));
    }
}
