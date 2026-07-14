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
use App\Models\StaffAttendance;
use App\Models\StaffSalaryPayment;
use App\Models\Expense;
use App\Models\Complaint;
use App\Models\Visitor;
use App\Models\AcBill;
use App\Models\PocketMoneyTransaction;
use App\Models\StudentDocument;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\StudentRegistration;
use App\Models\PaymentMode;
use App\Models\Discount;
use App\Enums\BillingPeriod;
use App\Enums\DiscountRecurrence;
use App\Enums\DiscountStatus;
use App\Enums\DiscountType;
use App\Services\Billing\AccountBillingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoHostelSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create the Owner first (if it's not super admin)
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

        // 3b. Extra branches (no student/room data) so the Phase 4 billing
        // flows — Align, Add-to-cycle, Renew-from-expired, volume tiers —
        // can be exercised against a real account in prod without the many
        // throwaway Test-N owners from Phase4TestingSeeder.
        //
        // Branch 3 "behind" the account anchor (Boys/Girls end 10mo out) by
        // 4 months, for Align / Add-to-cycle proration testing.
        $hostel3 = $this->createBranch([
            'name' => 'Sunrise Annex',
            'mobile' => '+919876543212',
            'email' => 'sunriseannex@example.com',
            'address' => 'Satellite Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'status' => 'active',
            'subscription_start' => now()->subMonths(6),
            'subscription_end' => now()->addMonths(6),
        ], $owner, populate: false);

        // Branch 4 expired well past the grace window, for the "renew an
        // expired branch" flow and the Customers-list Status filter.
        $hostel4 = $this->createBranch([
            'name' => 'Sunrise Outpost',
            'mobile' => '+919876543213',
            'email' => 'sunriseoutpost@example.com',
            'address' => 'Old City Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'status' => 'expired',
            'subscription_start' => now()->subMonths(14),
            'subscription_end' => now()->subMonths(2),
        ], $owner, populate: false);

        // Branch 5 co-terminated with Boys/Girls (same anchor), so the
        // account has 3 co-terminated branches — enough to trigger a
        // volume-tier discount on Renew all.
        $hostel5 = $this->createBranch([
            'name' => 'Sunrise PG',
            'mobile' => '+919876543214',
            'email' => 'sunrisepg@example.com',
            'address' => 'Vastrapur',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'status' => 'active',
            'subscription_start' => now()->subMonths(2),
            'subscription_end' => now()->addMonths(10),
        ], $owner, populate: false);

        // Wire the account/order spine (Phase 4 billing) so Account 360,
        // Align, Comp, etc. all resolve correctly for Ramesh Patel — the
        // legacy Subscription rows above don't create this on their own.
        $account = app(AccountBillingService::class)->accountFor($owner);
        app(AccountBillingService::class)->refreshAccountAnchor($account, BillingPeriod::Yearly);

        Discount::updateOrCreate(
            ['account_id' => $account->id, 'reason' => 'Loyalty — long-term customer (seed fixture)'],
            [
                'branch_id' => null,
                'recurrence' => DiscountRecurrence::EveryRenewal->value,
                'type' => DiscountType::Percentage->value,
                'value' => 5,
                'status' => DiscountStatus::Active->value,
            ],
        );

        // 4. Create Sub-Users (Staff Logins)
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

        // 5. A CO-ADMIN (second hostel_admin, NOT the owner) — the kind you add
        // from the Super Admin panel. Demonstrates P4 item 16: it shows on the
        // owner's Settings › Team & access with an "Admin" badge (owner can
        // disable/reset, not edit/delete), and never sees the owner in return.
        $coAdmin = User::updateOrCreate(
            ['mobile' => '+919876543004'],
            [
                'name' => 'Sanjay Co-Admin',
                'email' => 'sanjay.admin@example.com',
                'password' => Hash::make('password'),
                'role' => 'hostel_admin',
                'hostel_id' => $hostel1->id,
                'is_active' => true,
            ]
        );
        $coAdmin->hostels()->sync([$hostel1->id, $hostel2->id]); // access, but owner_id stays Ramesh

        $this->command->info('Demo branches seeded: Boys/Girls with full data; Annex/Outpost/PG empty for billing tests. Owner Ramesh + co-admin Sanjay + 3 staff.');
    }

    private function createBranch(array $data, User $owner, bool $populate = true): Hostel
    {
        $data['owner_name'] = $owner->name;
        $data['subscription_start'] = $data['subscription_start'] ?? now()->subMonths(2);
        $data['subscription_end'] = $data['subscription_end'] ?? now()->addMonths(10);

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

        // Invariants (P4 item 14): explicit owner FK, pivot access, and the
        // owner always has a primary branch.
        $hostel->update(['owner_id' => $owner->id]);
        $owner->hostels()->syncWithoutDetaching([$hostel->id]);
        if (! $owner->hostel_id) {
            $owner->forceFill(['hostel_id' => $hostel->id])->save();
        }
        app(\App\Services\HostelService::class)->seedPaymentModes($hostel);

        // --- ACTIVITY LOGS ---
        ActivityLog::create([
            'hostel_id' => $hostel->id,
            'user_id' => $owner->id,
            'action' => 'Created Hostel',
            'description' => "Hostel {$hostel->name} was set up.",
            'ip_address' => '127.0.0.1',
        ]);

        if (! $populate) {
            return $hostel;
        }

        // --- FLOORS & ROOMS & BEDS ---
        $floorNames = ['Ground Floor', 'First Floor'];
        $studentPool = [];
        $acRooms = [];

        foreach ($floorNames as $i => $fname) {
            $floor = Floor::firstOrCreate(
                ['hostel_id' => $hostel->id, 'name' => $fname],
                ['sort_order' => $i]
            );

            for ($r = 1; $r <= 2; $r++) {
                $sharing = [2, 3][$r - 1];
                $roomType = $r === 2 ? 'ac' : 'non_ac';
                $room = Room::firstOrCreate(
                    ['hostel_id' => $hostel->id, 'room_number' => ($i + 1) . '0' . $r],
                    [
                        'floor_id' => $floor->id,
                        'room_type' => $roomType,
                        'sharing' => $sharing,
                        'rent' => 4000 + ($sharing * 250),
                    ]
                );
                
                if ($roomType === 'ac') {
                    $acRooms[] = $room;
                }

                for ($b = 1; $b <= $sharing; $b++) {
                    $bed = Bed::firstOrCreate(
                        ['hostel_id' => $hostel->id, 'room_id' => $room->id, 'bed_number' => 'B' . $b],
                        ['status' => 'empty']
                    );
                    $studentPool[] = ['bed' => $bed, 'room' => $room];
                }
            }
        }

        // --- STUDENTS & INVOICES & PAYMENTS ---
        $names = ['Amit Shah', 'Vivek Joshi', 'Karan Mehta', 'Rahul Desai', 'Sahil Khan', 'Nikhil Rao'];
        if (str_contains($hostel->name, 'Girls')) {
            $names = ['Priya Patel', 'Riya Sharma', 'Anjali Desai', 'Neha Singh', 'Kavita Rao', 'Pooja Mehta'];
        }

        $toOccupy = (int) ceil(count($studentPool) * 0.6);
        $enrolledStudents = [];
        
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
            $enrolledStudents[] = $student;

            // Documents
            StudentDocument::firstOrCreate(
                ['student_id' => $student->id, 'type' => 'aadhaar'],
                ['file_path' => 'dummy/aadhaar.pdf', 'hostel_id' => $hostel->id]
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

            // Security Deposit
            if ($idx % 2 === 0) {
                \App\Models\SecurityDeposit::firstOrCreate([
                    'hostel_id' => $hostel->id,
                    'student_id' => $student->id,
                    'status' => 'collected',
                ], [
                    'amount' => 5000,
                    'payment_mode_id' => PaymentMode::where('hostel_id', $hostel->id)->first()->id ?? 1,
                    'receipt_number' => "SD-{$hostel->id}-{$student->id}",
                    'collected_on' => $student->join_date,
                    'created_by' => $owner->id,
                ]);
            }
            
            // Pocket Money
            PocketMoneyTransaction::create([
                'hostel_id' => $hostel->id,
                'student_id' => $student->id,
                'type' => 'deposit',
                'amount' => 2000,
                'note' => 'Initial deposit',
                'created_by' => $owner->id,
            ]);
            PocketMoneyTransaction::create([
                'hostel_id' => $hostel->id,
                'student_id' => $student->id,
                'type' => 'withdraw',
                'amount' => 500,
                'note' => 'Canteen expenses',
                'created_by' => $owner->id,
            ]);
        }
        
        // --- AC BILLS ---
        foreach ($acRooms as $acRoom) {
            AcBill::create([
                'hostel_id' => $hostel->id,
                'room_id' => $acRoom->id,
                'bill_month' => now()->subMonth()->format('Y-m'),
                'previous_reading' => 100,
                'current_reading' => 250,
                'total_units' => 150,
                'unit_price' => 10.0,
                'total_amount' => 1500,
            ]);
        }

        // --- STAFF & ATTENDANCE & SALARY ---
        $staff1 = Staff::create([
            'hostel_id' => $hostel->id,
            'name' => 'Govind Watchman',
            'designation' => 'Security Guard',
            'mobile' => '77000000' . $hostel->id,
            'monthly_salary' => 12000,
            'join_date' => now()->subMonths(5),
            'is_active' => true,
        ]);
        $staff2 = Staff::create([
            'hostel_id' => $hostel->id,
            'name' => 'Sita Cleaner',
            'designation' => 'Housekeeping',
            'mobile' => '77000011' . $hostel->id,
            'monthly_salary' => 10000,
            'join_date' => now()->subMonths(3),
            'is_active' => true,
        ]);

        // Attendance
        for ($i = 1; $i <= 5; $i++) {
            StaffAttendance::create([
                'hostel_id' => $hostel->id,
                'staff_id' => $staff1->id,
                'date' => now()->subDays($i),
                'status' => 'present',
            ]);
        }

        // Salary
        StaffSalaryPayment::create([
            'hostel_id' => $hostel->id,
            'staff_id' => $staff1->id,
            'amount' => 12000,
            'salary_month' => now()->subMonth()->startOfMonth(),
            'paid_on' => now()->subDays(5),
            'mode' => 'cash',
        ]);

        // --- EXPENSES ---
        Expense::create([
            'hostel_id' => $hostel->id,
            'category' => 'electricity',
            'title' => 'Monthly Electricity Bill',
            'amount' => 8500,
            'expense_date' => now()->subDays(10),
            'paid_to' => 'State Electricity Board',
            'mode' => 'upi',
            'recorded_by' => $owner->id,
        ]);
        Expense::create([
            'hostel_id' => $hostel->id,
            'category' => 'maintenance',
            'title' => 'Plumbing Fix',
            'amount' => 1500,
            'expense_date' => now()->subDays(4),
            'paid_to' => 'Local Plumber',
            'mode' => 'cash',
            'recorded_by' => $owner->id,
        ]);

        // --- COMPLAINTS ---
        if (count($enrolledStudents) > 0) {
            Complaint::create([
                'hostel_id' => $hostel->id,
                'student_id' => $enrolledStudents[0]->id,
                'title' => 'Fan is making noise',
                'category' => 'maintenance',
                'description' => 'The ceiling fan in my room makes a loud clicking sound.',
                'priority' => 'low',
                'status' => 'open',
                'created_by' => $owner->id,
            ]);
            
            if (count($enrolledStudents) > 1) {
                Complaint::create([
                    'hostel_id' => $hostel->id,
                    'student_id' => $enrolledStudents[1]->id,
                    'title' => 'Wi-Fi not working',
                    'category' => 'wifi',
                    'description' => 'Unable to connect to the router on the first floor.',
                    'priority' => 'high',
                    'status' => 'resolved',
                    'resolution' => 'Restarted router and reset password.',
                    'resolved_at' => now()->subDays(1),
                    'created_by' => $owner->id,
                ]);
            }
        }

        // --- VISITORS ---
        if (count($enrolledStudents) > 0) {
            Visitor::create([
                'hostel_id' => $hostel->id,
                'student_id' => $enrolledStudents[0]->id,
                'name' => 'Rakesh ' . explode(' ', $enrolledStudents[0]->name)[1],
                'mobile' => '9998887776',
                'purpose' => 'Meeting',
                'check_in' => now()->subHours(5),
                'check_out' => now()->subHours(3),
            ]);
        }

        // --- NOTIFICATIONS ---
        Notification::create([
            'hostel_id' => $hostel->id,
            'user_id' => $owner->id,
            'type' => 'info',
            'title' => 'New Hostel Setup',
            'message' => "Welcome to your new hostel: {$hostel->name}",
        ]);

        // --- STUDENT REGISTRATIONS (Inquiries) ---
        StudentRegistration::create([
            'hostel_id' => $hostel->id,
            'name' => 'Test Inquiry Student',
            'mobile' => '9988776655',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'status' => 'pending',
        ]);

        return $hostel;
    }
}
