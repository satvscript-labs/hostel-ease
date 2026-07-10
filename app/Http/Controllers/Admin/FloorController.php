<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFloorRequest;
use App\Models\Floor;
use App\Models\Hostel;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FloorController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(): View
    {
        $floors = Floor::ordered()
            ->with(['rooms' => fn ($q) => $q->orderBy('room_number')
                ->withCount(['beds', 'beds as occupied_beds_count' => fn ($q) => $q->where('status', 'occupied')])
            ])->get();

        $maxSharing = hostelease_max_room_sharing();

        return view('admin.builder.index', compact('floors', 'maxSharing'));
    }

    /**
     * The hostel's own ceiling on beds-per-room, set from the Layout
     * Builder's "Room Settings" panel — everything downstream (room
     * creation, the sharing stepper, fee-plan gate) reads this dynamically.
     */
    public function updateSharingSettings(Request $request)
    {
        $data = $request->validate([
            'max_room_sharing' => ['required', 'integer', 'min:1', 'max:'.config('hostelease.max_room_sharing_limit', 30)],
        ]);

        $hostel = Hostel::findOrFail(Tenant::id());
        $hostel->settings = array_merge($hostel->settings ?? [], ['max_room_sharing' => $data['max_room_sharing']]);
        $hostel->save();

        $this->logger->log('hostel.settings.update', "Set maximum room sharing to {$data['max_room_sharing']}", $hostel);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'max_room_sharing' => $data['max_room_sharing']]);
        }

        return back()->with('success', 'Room settings updated.');
    }

    public function reorder(Request $request)
    {
        $ids = $request->input('ordered_ids', []);
        
        foreach ($ids as $index => $id) {
            Floor::where('id', $id)->where('hostel_id', auth()->user()->hostel_id)->update(['sort_order' => $index + 1]);
        }
        
        return response()->json(['success' => true]);
    }

    public function store(StoreFloorRequest $request)
    {
        $data = $request->validated();
        $data['sort_order'] ??= (int) Floor::max('sort_order') + 1;

        $floor = Floor::create($data);

        $this->logger->log('floor.create', "Created floor {$floor->name}", $floor);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'floor' => $floor]);
        }
        return back()->with('success', 'Floor added successfully.');
    }

    public function update(StoreFloorRequest $request, Floor $floor)
    {
        $floor->update($request->validated());

        $this->logger->log('floor.update', "Updated floor {$floor->name}", $floor);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'floor' => $floor]);
        }
        return back()->with('success', 'Floor updated successfully.');
    }

    public function destroy(Request $request, Floor $floor)
    {
        if ($floor->rooms()->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Remove the rooms on this floor before deleting it.'], 422);
            }
            return back()->with('error', 'Remove the rooms on this floor before deleting it.');
        }

        $this->logger->log('floor.delete', "Deleted floor {$floor->name}", $floor);
        $floor->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Floor deleted.');
    }
}
