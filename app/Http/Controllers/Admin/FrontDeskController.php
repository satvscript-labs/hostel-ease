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
        // NOTE on pagination (roadmap C9): these two lists are intentionally NOT
        // paginated. The page search is client-side (Alpine x-show per row), so
        // server-side pagination would make the search silently cover only the
        // current page — a worse bug than the one it fixes. Both lists are
        // naturally bounded per hostel (visitors filter to a date; complaints to
        // an active workload), so the full fetch is cheap and the client search
        // stays correct. Revisit with server-side search together if a single
        // hostel ever accumulates thousands of rows.

        // ?date= is user input straight from the URL — $request->date() throws on
        // an unparseable value, which turned "?date=garbage" into a 500. Degrade
        // to "no date filter" instead (the view's date chip guards the same way).
        $visitDate = rescue(fn () => $request->date('date'), null, false);

        // Visitors data
        $visitors = Visitor::with('student')
            ->when($request->input('filter') === 'inside', fn ($q) => $q->inside())
            ->when($visitDate, fn ($q) => $q->whereDate('check_in', $visitDate))
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

        // Shared picker data for the Add Visitor / Log Complaint modals.
        // Shaped for <x-he-picker>: id + name + an optional secondary line
        // (mobile), which is also searchable inside the picker.
        $pickerStudents = Student::active()
            ->orderBy('name')
            ->get(['id', 'name', 'mobile'])
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'sub' => $s->mobile ? hostelease_phone($s->mobile) : null,
            ])
            ->all();

        return view('admin.frontdesk.index', compact('visitors', 'insideCount', 'complaints', 'complaintCounts', 'pickerStudents'));
    }
}
