<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\SemesterFee;
use App\Models\Student;
use App\Services\BedAssignmentService;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds dummy student data into the existing "Shraddha Girls" hostel,
 * and tidies the duplicate floor the admin created.
 */
class ShraddhaSeeder extends Seeder
{
    public function run(): void
    {
        $hostel = Hostel::where('name', 'like', '%Shraddha%')->first();

        if (! $hostel) {
            $this->command->warn('Shraddha hostel not found — skipping.');

            return;
        }

        $this->mergeDuplicateFloors($hostel->id);

        $assigner = app(BedAssignmentService::class);
        $payments = app(PaymentService::class);

        $names = [
            'Priya Sharma', 'Anjali Patel', 'Sneha Desai', 'Riya Mehta', 'Kavya Nair',
            'Pooja Joshi', 'Aishwarya Rao', 'Neha Gupta', 'Diya Shah', 'Isha Trivedi',
            'Megha Verma', 'Tanvi Iyer',
        ];

        // Empty beds in this hostel, ordered by room then bed number.
        $beds = Bed::where('hostel_id', $hostel->id)
            ->where('status', 'empty')
            ->with('room')
            ->get()
            ->sortBy([['room_id', 'asc'], ['bed_number', 'asc']])
            ->values();

        $created = 0;
        foreach ($names as $i => $name) {
            if (! isset($beds[$i])) {
                break;   // ran out of beds
            }

            $feeAmount = [30000, 33000, 36000][$i % 3];

            $student = Student::firstOrCreate(
                ['hostel_id' => $hostel->id, 'mobile' => '93000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                [
                    'name' => $name,
                    'father_mobile' => '92000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'occupation_type' => 'student',
                    'city' => 'Ahmedabad',
                    'state' => 'Gujarat',
                    'join_date' => now()->subDays(rand(15, 150)),
                    'leave_date' => $i % 6 === 0 ? now()->addDays(rand(5, 28)) : null,
                    'status' => 'active',
                    'room_preference' => ['AC', 'Non-AC'][$i % 2],
                    'sharing_preference' => ['Single', 'Double', 'Triple'][$i % 3],
                    'fee_amount' => $feeAmount,
                    'fee_frequency' => 'semester',
                ]
            );

            // Skip if already assigned somewhere.
            if ($student->activeAssignment()->exists()) {
                continue;
            }

            $assigner->assign($student, $beds[$i], [
                'join_date' => $student->join_date->toDateString(),
            ]);

            // Create initial invoice
            $invoice = \App\Models\Invoice::firstOrCreate(
                ['hostel_id' => $hostel->id, 'student_id' => $student->id, 'title' => 'Initial Semester Fee'],
                ['type' => 'fee', 'amount' => $feeAmount, 'balance' => $feeAmount, 'due_date' => now()->addDays(20), 'status' => 'pending']
            );

            if ($i % 2 === 0) {
                // Record partial payment
                $paidAmount = round($feeAmount / 2);
                $payment = \App\Models\Payment::create([
                    'hostel_id' => $hostel->id,
                    'student_id' => $student->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $paidAmount,
                    'mode' => ['cash', 'upi'][$i % 2],
                    'reference_number' => null,
                    'paid_on' => now()->subDays(rand(1, 20))->toDateString(),
                ]);
                $invoice->update([
                    'balance' => $invoice->amount - $paidAmount,
                    'status' => 'partial'
                ]);
            }

            $created++;
        }

        $this->command->info("Shraddha Girls: {$created} students assigned with semester fees.");
    }

    /**
     * Collapse floors that share a (case-insensitive) name into the earliest one.
     */
    protected function mergeDuplicateFloors(int $hostelId): void
    {
        $floors = DB::table('floors')->where('hostel_id', $hostelId)->whereNull('deleted_at')->get();
        $byName = $floors->groupBy(fn ($f) => strtoupper(trim($f->name)));

        foreach ($byName as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $keep = $group->sortBy('id')->first();
            $dupes = $group->where('id', '!=', $keep->id)->pluck('id')->all();

            // Move any rooms off the duplicates, then soft-delete the duplicates.
            DB::table('rooms')->whereIn('floor_id', $dupes)->update(['floor_id' => $keep->id]);
            DB::table('floors')->whereIn('id', $dupes)->update(['deleted_at' => now()]);

            $this->command->info('Merged duplicate floor(s) "'.$keep->name.'" → floor #'.$keep->id);
        }
    }
}
