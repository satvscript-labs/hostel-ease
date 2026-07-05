<?php

namespace App\Services;

use App\Models\AcBill;
use App\Models\Room;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Builds a monthly AC electricity bill for a room and splits it across the
 * occupants — either equally or among an admin-selected subset.
 */
class AcBillService
{
    /**
     * Students currently occupying the given room (active assignments).
     */
    public function occupants(Room $room)
    {
        return Student::active()
            ->whereHas('activeAssignment.bed', fn ($q) => $q->where('room_id', $room->id))
            ->orderBy('name')
            ->get();
    }

    /**
     * The previous reading to pre-fill — last bill's current unit for the room.
     */
    public function lastReading(Room $room): float
    {
        return (float) optional(
            $room->acBills()->orderByDesc('bill_month')->first()
        )->current_unit ?? 0.0;
    }

    /**
     * Create the bill and its per-student shares.
     *
     * @param  array<int>  $studentIds  required when distribution = 'selected'
     */
    public function create(Room $room, array $data, array $studentIds = []): AcBill
    {
        return DB::transaction(function () use ($room, $data, $studentIds) {
            $month = Carbon::parse($data['bill_month'].'-01')->startOfMonth();

            if ($room->acBills()->whereDate('bill_month', $month->toDateString())->exists()) {
                throw ValidationException::withMessages([
                    'bill_month' => "An AC bill for {$room->room_number} already exists for {$month->format('M Y')}.",
                ]);
            }

            $bill = new AcBill([
                'room_id' => $room->id,
                'bill_month' => $month,
                'previous_unit' => $data['previous_unit'],
                'current_unit' => $data['current_unit'],
                'unit_price' => $data['unit_price'],
                'distribution' => $data['distribution'],
            ]);
            $bill->hostel_id = $room->hostel_id;
            $bill->compute();
            $bill->save();

            // Resolve the students who share this bill.
            $occupants = $this->occupants($room);
            if ($data['distribution'] === 'selected') {
                $occupants = $occupants->whereIn('id', $studentIds)->values();
            }

            if ($occupants->isEmpty()) {
                throw ValidationException::withMessages([
                    'distribution' => 'No occupants found to share this bill.',
                ]);
            }

            $this->splitAmong($bill, $occupants);

            return $bill;
        });
    }

    /**
     * Divide the total evenly, pushing any rounding remainder onto the last share.
     */
    protected function splitAmong(AcBill $bill, $students): void
    {
        $count = $students->count();
        $each = round($bill->total_amount / $count, 2);
        $allocated = 0;

        $students->values()->each(function ($student, $i) use ($bill, $count, $each, &$allocated) {
            $amount = ($i === $count - 1)
                ? round($bill->total_amount - $allocated, 2)   // last absorbs the remainder
                : $each;
            $allocated += $amount;

            $share = $bill->shares()->make([
                'hostel_id' => $bill->hostel_id,
                'student_id' => $student->id,
                'amount' => $amount,
                'paid_amount' => 0,
            ]);
            $share->recalculate();
            $share->save();
        });
    }
}
