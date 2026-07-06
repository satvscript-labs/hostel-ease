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
        $hostel = Hostel::updateOrCreate(
            ['mobile' => '+919876543210'],
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
                'amount' => config('hostelease.subscription_amount', 5000),
                'payment_status' => 'paid',
                'payment_method' => 'upi',
                'transaction_number' => 'TXN'.now()->timestamp,
            ]
        );

        $admin = User::updateOrCreate(
            ['mobile' => '+919876543210'],
            [
                'hostel_id' => $hostel->id,
                'name' => 'Ramesh Patel',
                'email' => 'sunrise.admin@example.com',
                'password' => Hash::make('password'),
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
                    'room_preference' => $slot['room']->room_type === 'ac' ? 'AC' : 'Non-AC',
                    'sharing_preference' => ['Single', 'Double', 'Triple', 'Quad'][$slot['room']->sharing - 1] ?? 'Single',
                    'fee_amount' => $idx % 4 === 0 ? $slot['room']->rent : $slot['room']->rent * 6,
                    'fee_frequency' => $idx % 4 === 0 ? 'monthly' : 'semester',
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

            // Create a couple of demo invoices and payments
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

                // Use PaymentService to process payment
                app(\App\Services\PaymentService::class)->record([
                    'hostel_id' => $hostel->id,
                    'student_id' => $student->id,
                    'amount' => $invoice->amount,
                    'payment_type' => 'full',
                    'mode' => ['cash', 'upi'][$m % 2],
                    'paid_on' => $monthDate->copy()->addDays(6),
                    'collected_by' => $admin->id,
                    'reference_number' => "RCPT-{$hostel->id}-{$student->id}-{$m}",
                ]);
            }
        }

        // --- Additional Dummy Data for Ramesh Patel's Sunrise Boys Hostel ---

        // 1. Seed Staff members
        $warden = Staff::updateOrCreate(
            ['hostel_id' => $hostel->id, 'mobile' => '9888877777'],
            [
                'name' => 'Rajesh Sharma',
                'designation' => 'Warden',
                'monthly_salary' => 15000,
                'join_date' => now()->subMonths(3)->toDateString(),
                'address' => 'G-4, Sunrise Hostel Staff Quarters',
                'is_active' => true,
                'notes' => 'Manages day-to-day operations and student discipline'
            ]
        );

        $cook = Staff::updateOrCreate(
            ['hostel_id' => $hostel->id, 'mobile' => '9777766666'],
            [
                'name' => 'Manju Devi',
                'designation' => 'Cook',
                'monthly_salary' => 12000,
                'join_date' => now()->subMonths(3)->toDateString(),
                'address' => 'Navrangpura, Ahmedabad',
                'is_active' => true,
                'notes' => 'In charge of hostel mess and kitchen'
            ]
        );

        $guard = Staff::updateOrCreate(
            ['hostel_id' => $hostel->id, 'mobile' => '9666655555'],
            [
                'name' => 'Bahadur Singh',
                'designation' => 'Security Guard',
                'monthly_salary' => 10000,
                'join_date' => now()->subMonths(2)->toDateString(),
                'address' => 'Ranip, Ahmedabad',
                'is_active' => true,
                'notes' => 'Night shift security'
            ]
        );

        // 2. Staff Salary Payments (Last 2 months)
        for ($m = 1; $m <= 2; $m++) {
            $salaryMonth = now()->subMonths($m)->startOfMonth();
            
            StaffSalaryPayment::firstOrCreate(
                [
                    'hostel_id' => $hostel->id,
                    'staff_id' => $warden->id,
                    'salary_month' => $salaryMonth->toDateString(),
                ],
                [
                    'amount' => $warden->monthly_salary,
                    'paid_on' => $salaryMonth->addDays(5)->toDateString(),
                    'mode' => 'online',
                    'reference_number' => 'REF-WRD-' . $salaryMonth->format('Ym'),
                    'notes' => 'Salary paid on time'
                ]
            );

            StaffSalaryPayment::firstOrCreate(
                [
                    'hostel_id' => $hostel->id,
                    'staff_id' => $cook->id,
                    'salary_month' => $salaryMonth->toDateString(),
                ],
                [
                    'amount' => $cook->monthly_salary,
                    'paid_on' => $salaryMonth->addDays(5)->toDateString(),
                    'mode' => 'cash',
                    'notes' => 'Salary paid in cash'
                ]
            );
        }

        // 3. Seed Expenses
        Expense::firstOrCreate(
            [
                'hostel_id' => $hostel->id,
                'title' => 'Electricity Bill - June 2026',
                'expense_date' => now()->subDays(15)->toDateString(),
            ],
            [
                'category' => 'electricity',
                'amount' => 8500.00,
                'paid_to' => 'Torrent Power Ltd',
                'mode' => 'upi',
                'reference_number' => 'TXN-ELEC-7391',
                'notes' => 'Paid via GPay',
                'recorded_by' => $admin->id
            ]
        );

        Expense::firstOrCreate(
            [
                'hostel_id' => $hostel->id,
                'title' => 'Mess Grocery Supplies',
                'expense_date' => now()->subDays(5)->toDateString(),
            ],
            [
                'category' => 'food',
                'amount' => 4500.00,
                'paid_to' => 'Patel Provision Store',
                'mode' => 'cash',
                'notes' => 'Vegetables and dairy items',
                'recorded_by' => $admin->id
            ]
        );

        Expense::firstOrCreate(
            [
                'hostel_id' => $hostel->id,
                'title' => 'Fiber Internet Monthly Plan',
                'expense_date' => now()->startOfMonth()->toDateString(),
            ],
            [
                'category' => 'internet',
                'amount' => 1500.00,
                'paid_to' => 'Jio Fiber',
                'mode' => 'upi',
                'reference_number' => 'TXN-JIO-9922',
                'notes' => '100 Mbps broadband',
                'recorded_by' => $admin->id
            ]
        );

        Expense::firstOrCreate(
            [
                'hostel_id' => $hostel->id,
                'title' => 'Plumbing Repairs (Common Bathroom)',
                'expense_date' => now()->subDays(20)->toDateString(),
            ],
            [
                'category' => 'repairs',
                'amount' => 1200.00,
                'paid_to' => 'Vijay Plumber',
                'mode' => 'cash',
                'notes' => 'Fixed leaking flush valve',
                'recorded_by' => $admin->id
            ]
        );

        // 4. Seed Complaints (Get seeded student ids)
        $students = Student::where('hostel_id', $hostel->id)->get();
        if ($students->count() >= 3) {
            Complaint::firstOrCreate(
                [
                    'hostel_id' => $hostel->id,
                    'student_id' => $students[0]->id,
                    'title' => 'WiFi disconnected in Room ' . Room::find(BedAssignment::where('student_id', $students[0]->id)->first()?->bed?->room_id)?->room_number,
                ],
                [
                    'category' => 'internet',
                    'description' => 'The WiFi signal is extremely weak and frequently disconnects since yesterday.',
                    'priority' => 'medium',
                    'status' => 'resolved',
                    'resolution' => 'Replaced the Wi-Fi repeater in the corridor.',
                    'resolved_at' => now()->subDays(2),
                    'created_by' => $admin->id
                ]
            );

            Complaint::firstOrCreate(
                [
                    'hostel_id' => $hostel->id,
                    'student_id' => $students[1]->id,
                    'title' => 'Ceiling fan speed issue',
                ],
                [
                    'category' => 'room',
                    'description' => 'The ceiling fan regulator is broken and runs only at speed 5.',
                    'priority' => 'low',
                    'status' => 'in_progress',
                    'created_by' => $admin->id
                ]
            );

            Complaint::firstOrCreate(
                [
                    'hostel_id' => $hostel->id,
                    'student_id' => $students[2]->id,
                    'title' => 'Clogged shower drain',
                ],
                [
                    'category' => 'plumbing',
                    'description' => 'The water drains very slowly in the shower area, causing waterlogging.',
                    'priority' => 'high',
                    'status' => 'open',
                    'created_by' => $admin->id
                ]
            );
        }

        // 5. Seed Visitors
        if ($students->count() >= 1) {
            Visitor::firstOrCreate(
                [
                    'hostel_id' => $hostel->id,
                    'student_id' => $students[0]->id,
                    'name' => 'Suresh Shah',
                ],
                [
                    'mobile' => '9444433322',
                    'purpose' => 'Father visiting to deliver home food',
                    'id_proof' => 'Aadhaar Card: 4839 2019 4839',
                    'check_in' => now()->subDays(1)->setHour(10)->setMinute(0)->setSecond(0)->toDateTimeString(),
                    'check_out' => now()->subDays(1)->setHour(13)->setMinute(30)->setSecond(0)->toDateTimeString(),
                    'notes' => 'Checked out on time.'
                ]
            );

            Visitor::firstOrCreate(
                [
                    'hostel_id' => $hostel->id,
                    'student_id' => $students[1]->id,
                    'name' => 'Deepak Joshi',
                ],
                [
                    'mobile' => '9555544433',
                    'purpose' => 'Friend visiting for combined study',
                    'id_proof' => 'College ID Card: 2026-ENGG-93',
                    'check_in' => now()->setHour(14)->setMinute(15)->setSecond(0)->toDateTimeString(),
                    'check_out' => null, // Still inside
                    'notes' => 'Allowed till evening 8 PM.'
                ]
            );
        }

        $this->command->info('Demo hostel "Sunrise Boys Hostel" seeded — admin login +919876543210 / password');
    }
}

