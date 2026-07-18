<?php

namespace App\Services;

use App\Models\AcBill;
use App\Models\BedAssignment;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The room's meter, derived — never stored (owner decision, 2026-07-18:
 * Option A of _artifact/ac_meter_validation/00_report.md).
 *
 * An electricity meter only counts up, so any reading typed anywhere must be
 * ≥ the highest reading the room has ever recorded. Readings live in TWO
 * tables and this service is the one place that reads them together:
 *
 *   ac_bills.previous/current_reading   — the meter at each billing
 *   bed_assignments.join/leave_meter_reading — the meter at each move (W6.3)
 *
 * Soft-deleted bills still floor (owner decision): a reversed bill recorded a
 *  reading the meter physically reached — deleting the paperwork doesn't
 * un-move the meter.
 *
 * A meter CAN legitimately go down — hardware replaced or reset. That is the
 * `meterReset` override: allowed, but logged (`ac_meter.reset`) so the audit
 * trail shows who accepted the discontinuity and when.
 *
 * `$before` scopes the floor to readings strictly BEFORE a moment — a bill
 * that covers April must floor its start against what the meter showed before
 * April began, NOT against a mid-April move reading (that reading lives
 * inside the window the bill is about to explain). Kept as a parameter so a
 * future meter-history table can slot in without changing call-sites.
 */
class AcMeterService
{
    /** decimal(10,2) storage — compare with a hair of float tolerance. */
    private const EPSILON = 0.005;

    public function __construct(protected ActivityLogger $logger)
    {
    }

    /**
     * The highest reading recorded for the room — or null when it has none.
     * $before (exclusive) limits it to readings taken before that date.
     */
    public function lastReading(Room $room, ?CarbonInterface $before = null): ?float
    {
        return $this->computeForRooms([$room->id], $before)[$room->id] ?? null;
    }

    /**
     * Batch variant for payload building: room_id => highest reading (or null).
     * One pair of queries regardless of how many rooms are asked about.
     */
    public function lastReadingsForRooms(array $roomIds, ?CarbonInterface $before = null): array
    {
        return $this->computeForRooms($roomIds, $before);
    }

    /**
     * Per-bill floors for an already-loaded page of bills: bill_id => the
     * highest reading recorded strictly BEFORE that bill's month (the honest
     * floor for its previous_reading). The bill itself never floors itself —
     * its own month is excluded by the strict `<`.
     */
    public function floorsForBills(Collection $bills): array
    {
        if ($bills->isEmpty()) {
            return [];
        }

        $roomIds = $bills->pluck('room_id')->unique()->values()->all();

        // One fetch per source; per-bill filtering happens in PHP (a page is
        // ≤ 12 bills at hostel scale — two small queries beat 24).
        $billRows = AcBill::withTrashed()
            ->whereIn('room_id', $roomIds)
            ->get(['room_id', 'bill_month', 'current_reading', 'previous_reading'])
            ->groupBy('room_id');

        $moveRows = $this->moveRows($roomIds)->groupBy('room_id');

        $floors = [];
        foreach ($bills as $bill) {
            $monthStart = $bill->bill_month->copy()->startOfMonth();

            $floors[$bill->id] = $this->maxBefore(
                $billRows->get($bill->room_id, collect()),
                $moveRows->get($bill->room_id, collect()),
                $monthStart
            );
        }

        return $floors;
    }

    /**
     * Throw a friendly validation error when $reading is below the room's
     * floor — unless the operator explicitly confirmed a meter reset/
     * replacement, which is accepted and LOGGED.
     */
    public function assertNotBelow(
        Room $room,
        float $reading,
        string $field,
        bool $meterReset = false,
        ?CarbonInterface $before = null,
        string $context = 'move',
    ): void {
        $floor = $this->lastReading($room, $before);

        if ($floor === null || $reading >= $floor - self::EPSILON) {
            return; // fine — the meter only moved forward (or stood still)
        }

        if ($meterReset) {
            // Accepted discontinuity — record who declared the meter reset.
            $this->logger->log('ac_meter.reset', sprintf(
                'Meter reset/replaced accepted for Room %s (%s): %s → %s',
                $room->room_number, $context,
                number_format($floor, 2), number_format($reading, 2),
            ), $room);

            return;
        }

        throw ValidationException::withMessages([
            $field => __("Room :room's meter last read :floor — :reading is lower, and a meter only counts up. If the meter was actually reset or replaced, tick “Meter was reset / replaced” and submit again.", [
                'room' => $room->room_number,
                'floor' => number_format($floor, 2),
                'reading' => number_format($reading, 2),
            ]),
        ]);
    }

    // ── internals ────────────────────────────────────────────────────────

    /** room_id => max reading (or null), optionally before a cutoff date. */
    protected function computeForRooms(array $roomIds, ?CarbonInterface $before): array
    {
        $billRows = AcBill::withTrashed()
            ->whereIn('room_id', $roomIds)
            ->get(['room_id', 'bill_month', 'current_reading', 'previous_reading'])
            ->groupBy('room_id');

        $moveRows = $this->moveRows($roomIds)->groupBy('room_id');

        $out = [];
        foreach ($roomIds as $id) {
            $out[$id] = $this->maxBefore(
                $billRows->get($id, collect()),
                $moveRows->get($id, collect()),
                $before
            );
        }

        return $out;
    }

    /** Every move row (with its room_id) that carries at least one reading. */
    protected function moveRows(array $roomIds): Collection
    {
        return BedAssignment::query()
            ->join('beds', 'beds.id', '=', 'bed_assignments.bed_id')
            ->whereIn('beds.room_id', $roomIds)
            ->where(fn ($q) => $q->whereNotNull('bed_assignments.join_meter_reading')
                ->orWhereNotNull('bed_assignments.leave_meter_reading'))
            ->get([
                'beds.room_id',
                'bed_assignments.join_date', 'bed_assignments.join_meter_reading',
                'bed_assignments.leave_date', 'bed_assignments.leave_meter_reading',
            ]);
    }

    /**
     * MAX across both sources, honouring the (exclusive) cutoff. A bill for
     * month X ends at the start of month X+1, so it counts when its month is
     * strictly before the cutoff; a move reading counts when its own date is.
     */
    protected function maxBefore(Collection $billRows, Collection $moveRows, ?CarbonInterface $before): ?float
    {
        $candidates = [];

        foreach ($billRows as $row) {
            $month = \Illuminate\Support\Carbon::parse($row->bill_month)->startOfMonth();
            if ($before === null || $month->lt($before->copy()->startOfMonth())) {
                $candidates[] = (float) $row->current_reading;
                $candidates[] = (float) $row->previous_reading;
            }
        }

        foreach ($moveRows as $row) {
            if ($row->join_meter_reading !== null
                && ($before === null || ($row->join_date && \Illuminate\Support\Carbon::parse($row->join_date)->lt($before)))) {
                $candidates[] = (float) $row->join_meter_reading;
            }
            if ($row->leave_meter_reading !== null
                && ($before === null || ($row->leave_date && \Illuminate\Support\Carbon::parse($row->leave_date)->lt($before)))) {
                $candidates[] = (float) $row->leave_meter_reading;
            }
        }

        return $candidates === [] ? null : max($candidates);
    }
}
