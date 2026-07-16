<?php

namespace App\Services;

use App\Models\BedAssignment;
use App\Models\Room;
use Illuminate\Support\Carbon;

/**
 * The metered-segment AC split (W6.3, owner-required).
 *
 * v1 of this service was a pure day-ledger: reconstruct who occupied the room
 * each day, split each day's cost equally. Its hidden assumption — usage is
 * uniform across the month — is wrong the moment it matters: 30 units burned
 * before a mid-month join and 5 after means the joiner overpays badly if the
 * 35 are split by days.
 *
 * v2 anchors the split to REAL meter readings captured at occupancy changes
 * (bed_assignments.join_meter_reading / leave_meter_reading, entered at
 * assign/release/transfer — required for AC rooms, owner decision):
 *
 *  1. The month's readings (bill start → end) plus every event reading
 *     partition consumption into SEGMENTS of known units. Each segment has a
 *     fixed occupant set; its cost splits equally among them.
 *  2. Where a boundary has NO reading (rows from before the feature, skipped
 *     entries), the day-ledger still applies — but scoped to the enclosing
 *     metered segment only, and the breakdown flags that stretch "estimated
 *     by days". Exact where the data exists, honest where it doesn't.
 *  3. A reading that contradicts the meter (out of order, or outside the
 *     bill's own start/end) is IGNORED with a visible note and that boundary
 *     falls back to days. The split never crashes and never goes silently
 *     wrong on bad input.
 *  4. Money that accrued while the room sat empty spreads across the
 *     occupants proportionally (owner's market rule: the hostel pays no part
 *     of an AC bill), and rounding is remainder-correct: Σ(shares) == total,
 *     always.
 *
 * One service, one truth: the modal's live preview, store(), update(), and
 * the tests all call this — the numbers the owner reads before generating
 * ARE the numbers that get invoiced.
 */
class AcBillSplitService
{
    /**
     * Split the month's metered amount across its occupants.
     *
     * @param  float|null  $startReading  the bill's previous_reading (month start)
     * @param  float|null  $endReading    the bill's current_reading (month end)
     * @return array{
     *   students: array<int, array{student_id:int, name:string, days:int, from:string, to:string, share:float, joined_mid:bool, left:bool}>,
     *   segments: array<int, array{from:string, to:string, units:float|null, amount:float, occupants:int, metered:bool, estimated:bool}>,
     *   days_in_month:int, occupied_days:int, empty_days:int, total:float, note:?string
     * }
     */
    public function split(Room $room, Carbon $monthStart, float $totalAmount, ?float $startReading = null, ?float $endReading = null): array
    {
        $from = $monthStart->copy()->startOfMonth();
        $D = $from->daysInMonth;
        $total = round($totalAmount, 2);
        $notes = [];

        [$presence, $meta] = $this->presenceLedger($room, $from, $D);

        $occupiedDays = count(array_filter(range(1, $D), fn ($d) => ! empty($presence[$d])));
        $base = [
            'days_in_month' => $D,
            'occupied_days' => $occupiedDays,
            'empty_days' => $D - $occupiedDays,
            'total' => $total,
        ];

        if ($meta === []) {
            return $base + ['students' => [], 'segments' => [], 'note' => null];
        }

        // ── Boundary points: day-index (1-based, start-of-day) → reading|null.
        // Month start/end carry the bill's own readings; join events anchor at
        // the join day; leave events at the morning AFTER the leave day (the
        // student is present through their leave date).
        $points = [1 => ['reading' => $startReading, 'fixed' => true]];
        $points[$D + 1] = ['reading' => $endReading, 'fixed' => true];
        foreach ($meta as $m) {
            if ($m['join_day'] > 1) {
                $this->mergePoint($points, $m['join_day'], $m['join_reading'], $notes, $from);
            }
            if ($m['leave_day'] !== null && $m['leave_day'] < $D) {
                $this->mergePoint($points, $m['leave_day'] + 1, $m['leave_reading'], $notes, $from);
            }
        }
        ksort($points);

        // ── Monotonic sanity: the meter only goes up, and every event reading
        // must sit inside the bill's own [start, end]. Anything else is a
        // typo — ignore it OUT LOUD and let that boundary fall back to days.
        $metered = $startReading !== null && $endReading !== null && ($endReading - $startReading) > 0;
        if ($metered) {
            $last = $startReading;
            foreach ($points as $day => &$p) {
                if ($p['fixed'] || $p['reading'] === null) {
                    continue;
                }
                if ($p['reading'] + 1e-9 < $last || $p['reading'] > $endReading + 1e-9) {
                    $notes[] = __('Meter reading :r recorded on :d was ignored — it contradicts the meter sequence; that stretch is estimated by days.', [
                        'r' => rtrim(rtrim(number_format($p['reading'], 2), '0'), '.'),
                        'd' => $from->copy()->day(min($day, $D))->format('d M'),
                    ]);
                    $p['reading'] = null;
                } else {
                    $last = $p['reading'];
                }
            }
            unset($p);
        } else {
            // No usable bill readings (legacy callers): one estimated pseudo-
            // segment carrying the whole amount — v1 behaviour, flagged below.
            foreach ($points as $day => &$p) {
                if (! $p['fixed']) {
                    $p['reading'] = null;
                }
            }
            unset($p);
            $points[1]['reading'] = null;
            $points[$D + 1]['reading'] = null;
        }

        // ── Walk anchor-to-anchor; day-allocate inside each segment.
        $days = array_keys($points);
        $anchors = $metered
            ? array_values(array_filter($days, fn ($d) => $points[$d]['reading'] !== null))
            : [1, $D + 1];

        $perUnit = $metered ? $total / ($endReading - $startReading) : 0.0;

        $money = [];      // student_id => accrued share (pre-rounding)
        $unassigned = 0.0;
        $segments = [];

        for ($i = 0; $i + 1 < count($anchors); $i++) {
            $a = $anchors[$i];
            $b = $anchors[$i + 1];
            $segDays = $b - $a;
            $segUnits = $metered ? (float) ($points[$b]['reading'] - $points[$a]['reading']) : null;
            $segAmount = $metered ? round($segUnits * $perUnit, 4) : $total;

            // Interior boundaries (readings unknown) subdivide by days.
            $subStarts = array_values(array_filter($days, fn ($d) => $d >= $a && $d < $b));
            $estimated = count($subStarts) > 1;

            $segOccupantsMax = 0;
            foreach ($subStarts as $j => $s1) {
                $s2 = $subStarts[$j + 1] ?? $b;
                $subAmount = $segAmount * (($s2 - $s1) / $segDays);
                $occupants = array_keys($presence[$s1] ?? []);
                $segOccupantsMax = max($segOccupantsMax, count($occupants));

                if ($occupants === []) {
                    $unassigned += $subAmount;
                    continue;
                }
                $each = $subAmount / count($occupants);
                foreach ($occupants as $sid) {
                    $money[$sid] = ($money[$sid] ?? 0) + $each;
                }
            }

            $segments[] = [
                'from' => $from->copy()->day($a)->format('d M'),
                'to' => $from->copy()->day($b - 1)->format('d M'),
                'units' => $segUnits !== null ? round($segUnits, 2) : null,
                'amount' => round($segAmount, 2),
                'occupants' => $segOccupantsMax,
                'metered' => $metered,
                'estimated' => $estimated || ! $metered,
            ];
        }

        if (collect($segments)->contains(fn ($s) => $s['estimated'] && $s['metered'])) {
            $notes[] = __('Stretches without a recorded meter reading were estimated by days within their metered span.');
        }

        // ── Owner's market rule: occupants bear the FULL meter. Empty-time
        // money spreads proportionally to what each occupant consumed (falls
        // back to presence-days if all consumption was in empty stretches).
        if ($unassigned > 1e-9) {
            $pool = array_sum($money);
            foreach ($money as $sid => $v) {
                $money[$sid] = $pool > 1e-9
                    ? $v + $unassigned * ($v / $pool)
                    : $v + $unassigned * ($meta[$sid]['days'] / max(1, array_sum(array_column($meta, 'days'))));
            }
            $notes[] = __('Room was empty :n day(s) — that cost is spread across the occupants (AC bills are borne fully by occupants).', ['n' => $base['empty_days']]);
        }

        // ── Remainder-correct rounding: floor to paise, the largest share
        // absorbs the leftover — Σ(shares) == total, always.
        arsort($money);
        $students = [];
        $assigned = 0.0;
        foreach ($money as $sid => $raw) {
            $share = floor($raw * 100) / 100;
            $assigned += $share;
            $m = $meta[$sid];
            $students[] = [
                'student_id' => $sid,
                'name' => $m['name'],
                'days' => $m['days'],
                'from' => $from->copy()->day($m['first'])->format('d M'),
                'to' => $from->copy()->day($m['last'])->format('d M'),
                'share' => $share,
                'joined_mid' => $m['first'] > 1,
                'left' => $m['last'] < $D,
            ];
        }
        if ($students !== []) {
            $students[0]['share'] = round($students[0]['share'] + ($total - $assigned), 2);
        }

        return $base + [
            'students' => $students,
            'segments' => $segments,
            'note' => $notes === [] ? null : implode(' ', $notes),
        ];
    }

    /**
     * Reconstruct day-by-day presence + per-student metadata for the month
     * from bed-assignment HISTORY (join/leave dates + their meter readings) —
     * never from today's roster: a student who left still bears their days.
     *
     * @return array{0: array<int, array<int, true>>, 1: array<int, array>}
     */
    protected function presenceLedger(Room $room, Carbon $from, int $D): array
    {
        $to = $from->copy()->endOfMonth();

        $assignments = BedAssignment::with('student')
            ->whereHas('bed', fn ($q) => $q->where('room_id', $room->id))
            ->whereDate('join_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('leave_date')->orWhereDate('leave_date', '>=', $from))
            ->get()
            ->filter(fn ($a) => $a->student !== null);

        $presence = []; // day => [student_id => true]
        $meta = [];     // student_id => {name, days, first, last, join_day, leave_day, readings}
        foreach ($assignments as $a) {
            $join = Carbon::parse($a->join_date);
            $leave = $a->leave_date ? Carbon::parse($a->leave_date) : null;
            $startDay = max(1, $join->lt($from) ? 1 : $join->day);
            $endDay = ($leave === null || $leave->gt($to)) ? $D : $leave->day;
            if ($startDay > $endDay) {
                continue;
            }

            for ($d = $startDay; $d <= $endDay; $d++) {
                $presence[$d][$a->student_id] = true;
            }

            $sid = $a->student_id;
            $meta[$sid] ??= ['name' => $a->student->name, 'days' => 0, 'first' => $startDay, 'last' => $endDay,
                'join_day' => $join->lt($from) ? 1 : $join->day,
                'leave_day' => ($leave === null || $leave->gt($to)) ? null : $leave->day,
                'join_reading' => $join->lt($from) ? null : ($a->join_meter_reading !== null ? (float) $a->join_meter_reading : null),
                'leave_reading' => ($leave === null || $leave->gt($to)) ? null : ($a->leave_meter_reading !== null ? (float) $a->leave_meter_reading : null),
            ];
            $meta[$sid]['first'] = min($meta[$sid]['first'], $startDay);
            $meta[$sid]['last'] = max($meta[$sid]['last'], $endDay);
        }

        // Presence-day counts (a bed swap inside the room can't double-count).
        foreach ($meta as $sid => &$m) {
            $m['days'] = count(array_filter(range(1, $D), fn ($d) => isset($presence[$d][$sid])));
        }
        unset($m);

        return [$presence, $meta];
    }

    /** Merge an event boundary; conflicting same-day readings resolve out loud. */
    protected function mergePoint(array &$points, int $day, ?float $reading, array &$notes, Carbon $from): void
    {
        if (! isset($points[$day])) {
            $points[$day] = ['reading' => $reading, 'fixed' => false];

            return;
        }
        if ($points[$day]['fixed']) {
            return; // month start/end anchors always win
        }
        if ($reading === null) {
            return;
        }
        if ($points[$day]['reading'] === null) {
            $points[$day]['reading'] = $reading;
        } elseif (abs($points[$day]['reading'] - $reading) > 0.005) {
            $notes[] = __('Two different meter readings were recorded on :d — the higher one was used.', ['d' => $from->copy()->day(min($day, $from->daysInMonth))->format('d M')]);
            $points[$day]['reading'] = max($points[$day]['reading'], $reading);
        }
    }
}
