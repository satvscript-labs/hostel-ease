<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoHostelSeeder extends Seeder
{
    public function run(): void
    {
        $hostel = Hostel::updateOrCreate(
            ['mobile' => '9876543210'],
            [
                'name' => 'Sunrise Boys Hostel',
                'owner_name' => 'Ramesh Patel',
                'email' => 'sunrise@example.com',
                'address' => 'Near University Road',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'subscription_start' => now()->subMonths(2),
                'subscription_end' => now()->addMonths(10),
                'status' => 'active',
            ]
        );

        Subscription::firstOrCreate(
            ['hostel_id' => $hostel->id, 'start_date' => $hostel->subscription_start],
            [
                'plan' => '1_year',
                'end_date' => $hostel->subscription_end,
                'amount' => config('hsms.subscription_amount', 5000),
                'payment_status' => 'paid',
                'payment_method' => 'upi',
                'transaction_number' => 'TXN'.now()->timestamp,
            ]
        );

        $admin = User::updateOrCreate(
            ['mobile' => '9876543210'],
            [
                'hostel_id' => $hostel->id,
                'name' => 'Ramesh Patel',
                'email' => 'sunrise.admin@example.com',
                'password' => Hash::make('Password@123'),
                'role' => 'hostel_admin',
                'is_active' => true,
            ]
        );

        $admin->hostels()->syncWithoutDetaching([$hostel->id]);
        app(\App\Services\HostelService::class)->seedPaymentModes($hostel);

        // Floors -> Rooms -> Beds
        $floorNames = ['Ground Floor', 'First Floor', 'Second Floor'];
        $studentPool = [];

        foreach ($floorNames as $i => $fname) {
            $floor = Floor::firstOrCreate(
                ['hostel_id' => $hostel->id, 'name' => $fname],
                ['sort_order' => $i]
            );

            for ($r = 1; $r <= 3; $r++) {
                $sharing = [2, 3, 4][$r - 1];
                $room = Room::firstOrCreate(
                    ['hostel_id' => $hostel->id, 'room_number' => ($i + 1).'0'.$r],
                    [
                        'floor_id' => $floor->id,
                        'room_type' => $r === 3 ? 'ac' : 'non_ac',
                        'sharing' => $sharing,
                        'rent' => 4000 + ($sharing * 250),
                    ]
                );

                for ($b = 1; $b <= $sharing; $b++) {
                    $bed = Bed::firstOrCreate(
                        ['hostel_id' => $hostel->id, 'room_id' => $room->id, 'bed_number' => 'B'.$b],
                        ['status' => 'empty']
                    );
                    $studentPool[] = ['bed' => $bed, 'room' => $room];
                }
            }
        }

        // Occupy ~60% of beds with demo students.
        $names = ['Amit Shah', 'Vivek Joshi', 'Karan Mehta', 'Rahul Desai', 'Sahil Khan',
            'Nikhil Rao', 'Arjun Nair', 'Deep Trivedi', 'Mohit Verma', 'Yash Gandhi'];

        $toOccupy = (int) ceil(count($studentPool) * 0.6);
        foreach (array_slice($studentPool, 0, $toOccupy) as $idx => $slot) {
            $student = Student::firstOrCreate(
                ['hostel_id' => $hostel->id, 'mobile' => '90000000'.str_pad((string) $idx, 2, '0', STR_PAD_LEFT)],
                [
                    'name' => $names[$idx % count($names)],
                    'father_mobile' => '88000000'.str_pad((string) $idx, 2, '0', STR_PAD_LEFT),
                    'occupation_type' => $idx % 4 === 0 ? 'working' : 'student',
                    'city' => 'Ahmedabad',
                    'state' => 'Gujarat',
                    'join_date' => now()->subDays(rand(10, 120)),
                    'leave_date' => $idx % 5 === 0 ? now()->addDays(rand(3, 25)) : null,
                    'status' => 'active',
                ]
            );

            // Working professionals pay monthly; students pay per semester.
            $frequency = $student->occupation_type === 'working' ? 'monthly' : 'semester';
            $feeAmount = $student->occupation_type === 'working' ? $slot['room']->rent : $slot['room']->rent * 6;

            BedAssignment::firstOrCreate(
                ['hostel_id' => $hostel->id, 'bed_id' => $slot['bed']->id, 'student_id' => $student->id, 'is_active' => true],
                [
                    'join_date' => $student->join_date,
                    'leave_date' => $student->leave_date,
                    'fee_amount' => $feeAmount,
                    'fee_frequency' => $frequency,
                    'monthly_rent' => $frequency === 'monthly' ? $slot['room']->rent : 0,
                ]
            );

            $slot['bed']->update(['status' => 'occupied']);

            // A couple of demo payments spread across recent months.
            for ($m = 0; $m < 2; $m++) {
                Payment::firstOrCreate(
                    [
                        'hostel_id' => $hostel->id,
                        'student_id' => $student->id,
                        'receipt_number' => "RCPT-{$hostel->id}-{$student->id}-{$m}",
                    ],
                    [
                        'amount' => $slot['room']->rent,
                        'payment_type' => 'full',
                        'mode' => ['cash', 'upi'][$m % 2],
                        'paid_on' => now()->subMonths($m)->startOfMonth()->addDays(2),
                        'collected_by' => $admin->id,
                    ]
                );
            }
        }

        $this->command->info('Demo hostel "Sunrise Boys Hostel" seeded — admin login 9876543210 / Password@123');
    }
}
