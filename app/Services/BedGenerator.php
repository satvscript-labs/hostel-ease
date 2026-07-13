<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a room's beds in sync with its `sharing` count.
 *
 * Beds are numbered B1..Bn. Growing a room adds the missing beds; shrinking
 * removes the highest-numbered beds, but only those that are currently empty —
 * occupied/reserved/maintenance beds are preserved so history is never lost.
 */
class BedGenerator
{
    /**
     * Synchronise the room's beds to match its sharing value.
     *
     * @return array{created:int, removed:int, kept_blocked:int}
     */
    public function sync(Room $room): array
    {
        $target = (int) $room->sharing;

        return DB::transaction(function () use ($room, $target) {
            $beds = $room->beds()->orderByRaw('CAST(SUBSTRING(bed_number, 2) AS UNSIGNED)')->get();
            $current = $beds->count();

            $created = 0;
            $removed = 0;
            $keptBlocked = 0;

            if ($target > $current) {
                for ($i = $current + 1; $i <= $target; $i++) {
                    $bedNumber = 'B'.$i;

                    // Beds are soft-deleted when a room shrinks, so their
                    // (room_id, bed_number) row still occupies the unique
                    // index — growing back past that number must restore it
                    // rather than insert, or it collides with itself.
                    $existing = $room->beds()->withTrashed()->where('bed_number', $bedNumber)->first();

                    if ($existing) {
                        $existing->restore();
                        $existing->status = 'empty';
                        $existing->save();
                    } else {
                        $room->beds()->create([
                            'hostel_id' => $room->hostel_id,
                            'bed_number' => $bedNumber,
                            'status' => 'empty',
                        ]);
                    }

                    $created++;
                }
            } elseif ($target < $current) {
                // Remove from the top down, skipping any bed that is in use.
                foreach ($beds->reverse() as $bed) {
                    if ($removed >= ($current - $target)) {
                        break;
                    }

                    if ($bed->status === 'empty') {
                        $bed->delete();
                        $removed++;
                    } else {
                        $keptBlocked++;
                    }
                }
            }

            return compact('created', 'removed', 'keptBlocked');
        });
    }
}
