<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloorController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(): JsonResponse
    {
        $floors = Floor::ordered()->withCount('rooms')->get()->map(fn ($f) => [
            'id' => $f->id,
            'name' => $f->name,
            'sort_order' => $f->sort_order,
            'rooms_count' => $f->rooms_count,
        ]);

        return response()->json(['floors' => $floors]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['sort_order'] ??= (int) Floor::max('sort_order') + 1;
        $floor = Floor::create($data);
        $this->logger->log('floor.create', "Created floor {$floor->name}", $floor);

        return response()->json(['message' => 'Floor added.', 'id' => $floor->id], 201);
    }

    public function update(Request $request, int $floor): JsonResponse
    {
        $model = Floor::findOrFail($floor);
        $model->update($this->validateData($request));
        $this->logger->log('floor.update', "Updated floor {$model->name}", $model);

        return response()->json(['message' => 'Floor updated.']);
    }

    public function destroy(int $floor): JsonResponse
    {
        $model = Floor::findOrFail($floor);
        if ($model->rooms()->exists()) {
            return response()->json(['message' => 'Remove the rooms on this floor first.'], 422);
        }
        $this->logger->log('floor.delete', "Deleted floor {$model->name}", $model);
        $model->delete();

        return response()->json(['message' => 'Floor deleted.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);
    }
}
