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
                    $room->beds()->create([
                        'hostel_id' => $room->hostel_id,
                        'bed_number' => 'B'.$i,
                        'status' => 'empty',
                    ]);
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
