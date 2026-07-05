<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Room;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global instant search (students / rooms / beds) for the active branch.
 * Returns type + id so the app can navigate to the right screen.
 */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $digits = preg_replace('/\D+/', '', $q);
        $results = [];

        Student::query()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->when($digits !== '', fn ($x) => $x->orWhere('mobile', 'like', "%{$digits}%")))
            ->limit(8)->get()
            ->each(function ($s) use (&$results) {
                $results[] = ['type' => 'student', 'id' => $s->id, 'group' => 'Students', 'label' => $s->name, 'sub' => $s->mobile];
            });

        Room::with('floor')->where('room_number', 'like', "%{$q}%")->limit(6)->get()
            ->each(function ($r) use (&$results) {
                $results[] = ['type' => 'room', 'id' => $r->id, 'group' => 'Rooms', 'label' => "Room {$r->room_number}", 'sub' => $r->floor?->name];
            });

        Bed::with('room')->where('bed_number', 'like', "%{$q}%")->limit(6)->get()
            ->each(function ($b) use (&$results) {
                $results[] = ['type' => 'bed', 'id' => $b->id, 'group' => 'Beds', 'label' => "Bed {$b->bed_number}", 'sub' => "Room {$b->room?->room_number} · ".ucfirst($b->status)];
            });

        return response()->json(['results' => $results]);
    }
}
