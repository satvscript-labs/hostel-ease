<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\Room;
use App\Services\ActivityLogger;
use App\Services\BedGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function __construct(
        protected BedGenerator $bedGenerator,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(): JsonResponse
    {
        $rooms = Room::with('floor')
            ->withCount([
                'beds',
                'beds as occupied_beds_count' => fn ($q) => $q->where('status', 'occupied'),
            ])
            ->get()
            ->sortBy([['floor.sort_order', 'asc'], ['room_number', 'asc']])
            ->values()
            ->map(fn ($r) => [
                'id' => $r->id,
                'room_number' => $r->room_number,
                'room_type' => $r->room_type,
                'sharing' => $r->sharing,
                'floor_id' => $r->floor_id,
                'floor' => $r->floor?->name,
                'beds_count' => $r->beds_count,
                'occupied_beds_count' => $r->occupied_beds_count,
            ]);

        // Floors list for the room form dropdown.
        $floors = Floor::ordered()->get(['id', 'name']);

        return response()->json(['rooms' => $rooms, 'floors' => $floors, 'room_types' => config('hsms.room_types'), 'sharing_options' => config('hsms.sharing_options')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request, null);
        $room = Room::create($data);
        $result = $this->bedGenerator->sync($room);
        $this->logger->log('room.create', "Created room {$room->room_number} ({$result['created']} beds)", $room);

        return response()->json(['message' => "Room {$room->room_number} created with {$result['created']} beds.", 'id' => $room->id], 201);
    }

    public function update(Request $request, int $room): JsonResponse
    {
        $model = Room::findOrFail($room);
        $model->update($this->validateData($request, $model->id));
        $result = $this->bedGenerator->sync($model);
        $this->logger->log('room.update', "Updated room {$model->room_number}", $model);

        $msg = "Room {$model->room_number} updated.";
        if ($result['created']) {
            $msg .= " {$result['created']} bed(s) added.";
        }
        if ($result['removed']) {
            $msg .= " {$result['removed']} empty bed(s) removed.";
        }
        if ($result['keptBlocked']) {
            $msg .= " {$result['keptBlocked']} bed(s) kept (in use).";
        }

        return response()->json(['message' => $msg]);
    }

    public function destroy(int $room): JsonResponse
    {
        $model = Room::findOrFail($room);
        if ($model->beds()->where('status', 'occupied')->exists()) {
            return response()->json(['message' => 'This room has occupied beds. Release the students first.'], 422);
        }
        $this->logger->log('room.delete', "Deleted room {$model->room_number}", $model);
        $model->delete();

        return response()->json(['message' => 'Room deleted.']);
    }

    protected function validateData(Request $request, ?int $roomId): array
    {
        return $request->validate([
            'floor_id' => ['required', Rule::exists('floors', 'id')->where('hostel_id', Tenant::id())],
            'room_number' => [
                'required', 'string', 'max:50',
                Rule::unique('rooms', 'room_number')->where('hostel_id', Tenant::id())->whereNull('deleted_at')->ignore($roomId),
            ],
            'room_type' => ['required', Rule::in(array_keys(config('hsms.room_types')))],
            'sharing' => ['required', 'integer', Rule::in(config('hsms.sharing_options'))],
        ], [
            'room_number.unique' => 'A room with this number already exists in your hostel.',
        ]);
    }
}
