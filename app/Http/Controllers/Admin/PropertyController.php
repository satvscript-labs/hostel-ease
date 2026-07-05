<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Floor;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyController extends Controller
{
    /**
     * Unified Property Board serving the liquid UI
     */
    public function index(Request $request): View
    {
        // 1. Eager load the entire property hierarchy for the visual board
        $floors = Floor::with(['rooms' => function($q) {
            $q->orderBy('room_number');
        }, 'rooms.beds' => function($q) {
            $q->orderBy('bed_number');
        }, 'rooms.beds.activeAssignment.student'])->ordered()->get();

        // 2. Global stats for the Bento dashboard header
        $totalBeds = Bed::count();
        $occupied = Bed::where('status', 'occupied')->count();
        $vacant = Bed::where('status', 'available')->count();
        $maintenance = Bed::where('status', 'maintenance')->count();

        // 3. Unassigned active students for the quick-assign modal
        $unassignedStudents = Student::where('status', 'active')
            ->whereDoesntHave('activeAssignment')
            ->orderBy('name')
            ->get();

        return view('admin.property.index', compact(
            'floors',
            'totalBeds',
            'occupied',
            'vacant',
            'maintenance',
            'unassignedStudents'
        ));
    }
}
