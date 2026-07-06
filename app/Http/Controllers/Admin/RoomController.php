<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Models\Floor;
use App\Models\Room;
use App\Services\ActivityLogger;
use App\Services\BedGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function __construct(
        protected BedGenerator $bedGenerator,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(): View
    {
        $rooms = Room::with('floor')
            ->withCount([
                'beds',
                'beds as occupied_beds_count' => fn ($q) => $q->where('status', 'occupied'),
            ])
            ->get()
            ->sortBy([['floor.sort_order', 'asc'], ['room_number', 'asc']]);

        return view('admin.rooms.index', compact('rooms'));
    }

    public function create(): View
    {
        $floors = Floor::ordered()->get();

        if ($floors->isEmpty()) {
            return view('admin.rooms.create', ['floors' => $floors])
                ->with('warning', 'Add a floor first.');
        }

        return view('admin.rooms.create', compact('floors'));
    }

    public function store(StoreRoomRequest $request)
    {
        $room = Room::create($request->validated());

        $result = $this->bedGenerator->sync($room);
        $this->logger->log('room.create', "Created room {$room->room_number} ({$result['created']} beds)", $room);

        if ($request->wantsJson()) {
            $room->loadCount([
                'beds',
                'beds as occupied_beds_count' => fn ($q) => $q->where('status', 'occupied'),
            ]);
            return response()->json(['success' => true, 'room' => $room]);
        }
        return redirect()->route('admin.rooms.index')
            ->with('success', "Room {$room->room_number} created with {$result['created']} beds.");
    }

    public function edit(Room $room): View
    {
        $floors = Floor::ordered()->get();
        $room->loadCount(['beds', 'beds as occupied_beds_count' => fn ($q) => $q->where('status', 'occupied')]);

        return view('admin.rooms.edit', compact('room', 'floors'));
    }

    public function update(StoreRoomRequest $request, Room $room)
    {
        $room->update($request->validated());

        $result = $this->bedGenerator->sync($room);
        $this->logger->log('room.update', "Updated room {$room->room_number}", $room);

        if ($request->wantsJson()) {
            $room->loadCount([
                'beds',
                'beds as occupied_beds_count' => fn ($q) => $q->where('status', 'occupied'),
            ]);
            return response()->json(['success' => true, 'room' => $room]);
        }

        $msg = "Room {$room->room_number} updated.";
        if ($result['created']) {
            $msg .= " {$result['created']} bed(s) added.";
        }
        if ($result['removed']) {
            $msg .= " {$result['removed']} empty bed(s) removed.";
        }
        if ($result['keptBlocked']) {
            $msg .= " {$result['keptBlocked']} bed(s) kept (in use) - free them to reduce sharing further.";
        }

        return redirect()->route('admin.rooms.index')->with('success', $msg);
    }

    public function destroy(Request $request, Room $room)
    {
        if ($room->beds()->where('status', 'occupied')->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'This room has occupied beds. Release the students first.'], 422);
            }
            return back()->with('error', 'This room has occupied beds. Release the students first.');
        }

        $this->logger->log('room.delete', "Deleted room {$room->room_number}", $room);
        $room->delete();   // beds cascade via FK

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Room deleted.');
    }
}
