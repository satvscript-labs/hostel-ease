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
use App\Models\Staff;
use App\Models\StaffSalaryPayment;
use App\Models\Expense;
use App\Models\Complaint;
use App\Models\Visitor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoHostelSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create the Owner first
        $owner = User::updateOrCreate(
            ['mobile' => '+919876543210'],
            [
                'name' => 'Ramesh Patel',
                'email' => 'sunrise.admin@example.com',
                'password' => Hash::make('password'),
                'role' => 'hostel_admin',
                'is_active' => true,
            ]
        );

        // 2. Create Branch 1: Sunrise Boys Hostel
        $hostel1 = $this->createBranch([
            'name' => 'Sunrise Boys Hostel',
            'mobile' => '+919876543210',
            'email' => 'sunrise@example.com',
            'address' => 'Near University Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'status' => 'active',
        ], $owner);

        // 3. Create Branch 2: Sunrise Girls Hostel
        $hostel2 = $this->createBranch([
            'name' => 'Sunrise Girls Hostel',
            'mobile' => '+919876543211',
            'email' => 'sunrisegirls@example.com',
            'address' => 'Navrangpura',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'status' => 'active',
        ], $owner);

        // 4. Create Sub-Users (Staff)
        $manager1 = User::updateOrCreate(
            ['mobile' => '+919876543001'],
            [
                'name' => 'Amit Manager (Boys)',
                'email' => 'amit.manager@example.com',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'is_active' => true,
            ]
        );
        $manager1->hostels()->sync([$hostel1->id]);

        $manager2 = User::updateOrCreate(
            ['mobile' => '+919876543002'],
            [
                'name' => 'Neha Manager (Girls)',
                'email' => 'neha.manager@example.com',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'is_active' => true,
            ]
        );
        $manager2->hostels()->sync([$hostel2->id]);

        $accountant = User::updateOrCreate(
            ['mobile' => '+919876543003'],
            [
                'name' => 'Rahul Accountant (Both)',
                'email' => 'rahul.acc@example.com',
                'password' => Hash::make('password'),
                'role' => 'accountant',
                'is_active' => true,
            ]
        );
        $accountant->hostels()->sync([$hostel1->id, $hostel2->id]);

        $this->command->info('Demo branches "Sunrise Boys Hostel" and "Sunrise Girls Hostel" seeded!');
        $this->command->info('Owner login: +919876543210 / password');
        $this->command->info('Manager 1 (Boys) login: +919876543001 / password');
        $this->command->info('Manager 2 (Girls) login: +919876543002 / password');
        $this->command->info('Accountant (Both) login: +919876543003 / password');
    }

    private function createBranch(array $data, User $owner): Hostel
    {
        $data['owner_name'] = $owner->name;
        $data['subscription_start'] = now()->subMonths(2);
        $data['subscription_end'] = now()->addMonths(10);

        $hostel = Hostel::updateOrCreate(['mobile' => $data['mobile']], $data);

        Subscription::firstOrCreate(
            ['hostel_id' => $hostel->id, 'start_date' => $hostel->subscription_start],
            [
                'plan' => '1_year',
                'end_date' => $hostel->subscription_end,
                'amount' => config('hostelease.subscription_amount', 5000),
                'payment_status' => 'paid',
                'payment_method' => 'upi',
                'transaction_number' => 'TXN' . now()->timestamp . $hostel->id,
            ]
        );

        $owner->hostels()->syncWithoutDetaching([$hostel->id]);
        app(\App\Services\HostelService::class)->seedPaymentModes($hostel);

        // Floors -> Rooms -> Beds
        $floorNames = ['Ground Floor', 'First Floor'];
        $studentPool = [];

        foreach ($floorNames as $i => $fname) {
            $floor = Floor::firstOrCreate(
                ['hostel_id' => $hostel->id, 'name' => $fname],
                ['sort_order' => $i]
            );

            for ($r = 1; $r <= 2; $r++) {
                $sharing = [2, 3][$r - 1];
                $room = Room::firstOrCreate(
                    ['hostel_id' => $hostel->id, 'room_number' => ($i + 1) . '0' . $r],
                    [
                        'floor_id' => $floor->id,
                        'room_type' => $r === 2 ? 'ac' : 'non_ac',
                        'sharing' => $sharing,
                        'rent' => 4000 + ($sharing * 250),
                    ]
                );

                for ($b = 1; $b <= $sharing; $b++) {
                    $bed = Bed::firstOrCreate(
                        ['hostel_id' => $hostel->id, 'room_id' => $room->id, 'bed_number' => 'B' . $b],
                        ['status' => 'empty']
                    );
                    $studentPool[] = ['bed' => $bed, 'room' => $room];
                }
            }
        }

        // Occupy ~60% of beds with demo students.
        $names = ['Amit Shah', 'Vivek Joshi', 'Karan Mehta', 'Rahul Desai', 'Sahil Khan', 'Nikhil Rao'];
        if (str_contains($hostel->name, 'Girls')) {
            $names = ['Priya Patel', 'Riya Sharma', 'Anjali Desai', 'Neha Singh', 'Kavita Rao', 'Pooja Mehta'];
        }

        $toOccupy = (int) ceil(count($studentPool) * 0.6);
        foreach (array_slice($studentPool, 0, $toOccupy) as $idx => $slot) {
            $mobileSuffix = $hostel->id . str_pad((string) $idx, 2, '0', STR_PAD_LEFT);
            $student = Student::firstOrCreate(
                ['hostel_id' => $hostel->id, 'mobile' => '900000' . $mobileSuffix],
                [
                    'name' => $names[$idx % count($names)],
                    'father_mobile' => '880000' . $mobileSuffix,
                    'occupation_type' => $idx % 2 === 0 ? 'working' : 'student',
                    'city' => 'Ahmedabad',
                    'state' => 'Gujarat',
                    'join_date' => now()->subDays(rand(10, 120)),
                    'leave_date' => null,
                    'status' => 'active',
                    'room_preference' => $slot['room']->room_type === 'ac' ? 'AC' : 'Non-AC',
                    'sharing_preference' => ['Single', 'Double', 'Triple', 'Quad'][$slot['room']->sharing - 1] ?? 'Single',
                    'fee_amount' => $slot['room']->rent * ($idx % 2 === 0 ? 1 : 6),
                    'fee_frequency' => $idx % 2 === 0 ? 'monthly' : 'semester',
                ]
            );

            BedAssignment::firstOrCreate(
                ['hostel_id' => $hostel->id, 'bed_id' => $slot['bed']->id, 'student_id' => $student->id, 'is_active' => true],
                [
                    'join_date' => $student->join_date,
                    'leave_date' => $student->leave_date,
                ]
            );

            $slot['bed']->update(['status' => 'occupied']);

            // Invoices and payments
            for ($m = 0; $m < 2; $m++) {
                $monthDate = now()->subMonths($m)->startOfMonth();
                $type = $student->occupation_type === 'working' ? 'rent' : 'fee';
                $title = $type === 'rent' ? 'Rent - ' . $monthDate->format('M Y') : 'Semester Fee - S' . ($m + 1);

                $invoice = \App\Models\Invoice::firstOrCreate(
                    [
                        'hostel_id' => $hostel->id,
                        'student_id' => $student->id,
                        'type' => $type,
                        'title' => $title,
                    ],
                    [
                        'amount' => $slot['room']->rent * ($type === 'rent' ? 1 : 6),
                        'due_date' => $monthDate->copy()->addDays(5),
                    ]
                );

                app(\App\Services\PaymentService::class)->record([
                    'hostel_id' => $hostel->id,
                    'student_id' => $student->id,
                    'amount' => $invoice->amount,
                    'payment_type' => 'full',
                    'mode' => ['cash', 'upi'][$m % 2],
                    'paid_on' => $monthDate->copy()->addDays(6),
                    'collected_by' => $owner->id,
                    'reference_number' => "RCPT-{$hostel->id}-{$student->id}-{$m}",
                ]);
            }

            // Security Deposit for every other student
            if ($idx % 2 === 0) {
                \App\Models\SecurityDeposit::firstOrCreate([
                    'hostel_id' => $hostel->id,
                    'student_id' => $student->id,
                    'status' => 'collected',
                ], [
                    'amount' => 5000,
                    'payment_mode_id' => \App\Models\PaymentMode::first()->id ?? 1,
                    'receipt_number' => "SD-{$hostel->id}-{$student->id}",
                    'collected_on' => $student->join_date,
                    'created_by' => $owner->id,
                ]);
            }
        }

        return $hostel;
    }
}

