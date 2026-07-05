<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFloorRequest;
use App\Models\Floor;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FloorController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(): View
    {
        $floors = Floor::ordered()->withCount(['rooms'])->get();

        return view('admin.floors.index', compact('floors'));
    }

    public function store(StoreFloorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] ??= (int) Floor::max('sort_order') + 1;

        $floor = Floor::create($data);

        $this->logger->log('floor.create', "Created floor {$floor->name}", $floor);

        return back()->with('success', 'Floor added successfully.');
    }

    public function update(StoreFloorRequest $request, Floor $floor): RedirectResponse
    {
        $floor->update($request->validated());

        $this->logger->log('floor.update', "Updated floor {$floor->name}", $floor);

        return back()->with('success', 'Floor updated successfully.');
    }

    public function destroy(Floor $floor): RedirectResponse
    {
        if ($floor->rooms()->exists()) {
            return back()->with('error', 'Remove the rooms on this floor before deleting it.');
        }

        $this->logger->log('floor.delete', "Deleted floor {$floor->name}", $floor);
        $floor->delete();

        return back()->with('success', 'Floor deleted.');
    }
}
