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
                    // Same shape SecurityDepositController::nextReceiptNumber()
                    // produces (SD-{hostel}-00001) — seeded rows must be
                    // indistinguishable from ones the app made.
                    'receipt_number' => "SD-{$hostel->id}-".str_pad((string) ($idx + 1), 5, '0', STR_PAD_LEFT),
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
        // A real, fully-formed bill for each stock AC room: generated through
        // the SAME split service the app uses, so it carries genuine
        // per-student invoices and a stored breakdown. The old seeder wrote a
        // bare AcBill row with no invoices — a bill nobody owed, which made
        // the list show "0 students / ₹0 collected" and the expandable row
        // empty.
        foreach ($acRooms as $acRoom) {
            $this->generateAcBill($hostel, $acRoom, now()->subMonth(), prev: 100, curr: 250, rate: 10.0);
        }

        // --- AC TEST SCENARIOS (W6.3) ---
        // Purpose-built occupancy + meter history for hand-testing the split.
        // Only the primary branch gets these, so the numbers in
        // _artifact/ui_ux_audit/03_testing_w6.3.md are unambiguous.
        if (! str_contains($hostel->name, 'Girls')) {
            $this->seedAcScenarios($hostel);
            $this->seedCustodyScenarios($hostel, $owner);
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

    /**
     * Generate a REAL AC bill — same split service, same invoice shape the
     * controller produces. Seeded bills must be indistinguishable from ones
     * the app made, or they teach the tester the wrong thing.
     */
    protected function generateAcBill(Hostel $hostel, Room $room, \Illuminate\Support\Carbon $month, float $prev, float $curr, float $rate): ?AcBill
    {
        $units = $curr - $prev;
        $amount = round($units * $rate, 2);
        $monthStart = $month->copy()->startOfMonth();

        $breakdown = app(\App\Services\AcBillSplitService::class)
            ->split($room, $monthStart, $amount, $prev, $curr);

        if ($breakdown['students'] === []) {
            return null; // nobody in the room that month — nothing to bill
        }

        $bill = AcBill::create([
            'hostel_id' => $hostel->id,
            'room_id' => $room->id,
            'bill_month' => $monthStart->format('Y-m-d'),
            'previous_reading' => $prev,
            'current_reading' => $curr,
            'total_units' => $units,
            'unit_price' => $rate,
            'total_amount' => $amount,
            'split_breakdown' => $breakdown,
        ]);

        foreach ($breakdown['students'] as $s) {
            \App\Models\Invoice::create([
                'hostel_id' => $hostel->id,
                'student_id' => $s['student_id'],
                'type' => 'ac',
                'ac_bill_id' => $bill->id,
                'title' => "AC Bill — {$monthStart->format('M Y')} (Room {$room->room_number}) · {$s['days']} of {$breakdown['days_in_month']} days",
                'amount' => $s['share'],
                'status' => 'pending',
                'due_date' => now()->addDays(15)->toDateString(),
                'is_generated_by_system' => true,
            ]);
        }

        return $bill;
    }

    /**
     * W6.4 custody scenarios — the states the three money-custody pages have
     * to render but that ordinary demo data never produces: a settled
     * deposit, a leaver who still holds money, a lent-out (negative) wallet,
     * and payment modes on both sides of the delete/deactivate guard.
     *
     * Without these the Refunded/Deducted tiles read ₹0 forever, the revert
     * button never appears, the "Departed with money" filter matches nothing,
     * and every mode card looks identical.
     */
    protected function seedCustodyScenarios(Hostel $hostel, User $owner): void
    {
        $mode = PaymentMode::where('hostel_id', $hostel->id)->value('id');

        // ── A SETTLED deposit: ₹5,000 = ₹4,000 back + ₹1,000 kept.
        // Satisfies the full-settlement rule exactly (refunded + deducted ==
        // amount), which is what the app now enforces on every refund.
        $leaver = Student::firstOrCreate(
            ['hostel_id' => $hostel->id, 'mobile' => '9760000001'],
            ['name' => 'Rohit Kulkarni', 'occupation_type' => 'student', 'status' => 'left',
                'city' => 'Ahmedabad', 'state' => 'Gujarat',
                'join_date' => now()->subMonths(8), 'leave_date' => now()->subMonths(1),
                'fee_amount' => 5000, 'fee_frequency' => 'monthly']
        );
        \App\Models\SecurityDeposit::firstOrCreate(
            ['hostel_id' => $hostel->id, 'student_id' => $leaver->id],
            [
                'amount' => 5000, 'status' => 'refunded', 'payment_mode_id' => $mode,
                'receipt_number' => "SD-{$hostel->id}-90001",
                'collected_on' => now()->subMonths(8),
                'refunded_on' => now()->subMonths(1),
                'refunded_amount' => 4000, 'deducted_amount' => 1000,
                'refund_note' => 'Mattress damage deducted; balance returned in cash.',
                'created_by' => $owner->id,
            ]
        );

        // ── A leaver who still HOLDS pocket money: the row the old page hid
        // while its footer total still counted the balance.
        foreach ([['deposit', 3000, 'Initial deposit'], ['withdraw', 1200, 'Canteen']] as [$type, $amt, $note]) {
            PocketMoneyTransaction::create(['hostel_id' => $hostel->id, 'student_id' => $leaver->id,
                'type' => $type, 'amount' => $amt, 'note' => $note, 'created_by' => $owner->id]);
        }

        // ── A LENT-OUT wallet (negative balance) — allowed by design, and the
        // "Lent Out" filter needs something to match.
        $borrower = Student::where('hostel_id', $hostel->id)->where('status', 'active')->orderBy('id')->first();
        if ($borrower) {
            PocketMoneyTransaction::create(['hostel_id' => $hostel->id, 'student_id' => $borrower->id,
                'type' => 'withdraw', 'amount' => 3000, 'note' => 'Emergency cash advance',
                'created_by' => $owner->id]);
        }

        // ── Payment modes on both sides of the W6.4 guard: one INACTIVE (still
        // naming its history), one NEVER USED (the only kind that may delete).
        PaymentMode::firstOrCreate(
            ['hostel_id' => $hostel->id, 'code' => 'old_wallet'],
            ['name' => 'Paytm Wallet (retired)', 'is_active' => false, 'requires_reference' => true, 'sort_order' => 90]
        );
        PaymentMode::firstOrCreate(
            ['hostel_id' => $hostel->id, 'code' => 'demand_draft'],
            ['name' => 'Demand Draft', 'is_active' => true, 'requires_reference' => true, 'sort_order' => 91]
        );
    }

    /**
     * AC split test scenarios (W6.3) — occupancy + meter history planted for
     * LAST MONTH, deliberately left UNBILLED so the tester generates each bill
     * through the UI and checks the shares against the expected numbers in
     * _artifact/ui_ux_audit/03_testing_w6.3.md.
     *
     * The readings are chosen so a day-based split and a metered split give
     * VERY different answers — that gap is what proves the meter is being
     * honoured. Every number here is mirrored in the testing doc; change one,
     * change both.
     */
    protected function seedAcScenarios(Hostel $hostel): void
    {
        $floor = Floor::firstOrCreate(
            ['hostel_id' => $hostel->id, 'name' => 'Second Floor (AC Test Lab)'],
            ['sort_order' => 2]
        );

        $month = now()->subMonth()->startOfMonth();  // the month under test
        $mid = $month->copy()->day(16);              // every scenario swaps/joins on the 16th
        $before = $month->copy()->subMonths(2);      // "already living here" join date

        $room = function (string $number, int $sharing) use ($hostel, $floor): Room {
            $r = Room::firstOrCreate(
                ['hostel_id' => $hostel->id, 'room_number' => $number],
                ['floor_id' => $floor->id, 'room_type' => 'ac', 'sharing' => $sharing, 'rent' => 5000]
            );
            app(\App\Services\BedGenerator::class)->sync($r);

            return $r->fresh('beds');
        };

        $n = 0;
        $student = function (string $name) use ($hostel, &$n): Student {
            $n++;

            return Student::firstOrCreate(
                ['hostel_id' => $hostel->id, 'mobile' => '9770000'.str_pad((string) ($hostel->id * 100 + $n), 3, '0', STR_PAD_LEFT)],
                ['name' => $name, 'occupation_type' => 'student', 'status' => 'active',
                    'city' => 'Ahmedabad', 'state' => 'Gujarat',
                    'join_date' => now()->subMonths(3), 'fee_amount' => 5000, 'fee_frequency' => 'monthly']
            );
        };

        $stay = function (Room $r, int $bedIndex, Student $s, $join, $leave, ?float $joinRead, ?float $leaveRead) use ($hostel) {
            BedAssignment::create([
                'hostel_id' => $hostel->id,
                'bed_id' => $r->beds[$bedIndex]->id,
                'student_id' => $s->id,
                'join_date' => $join,
                'leave_date' => $leave,
                'join_meter_reading' => $joinRead,
                'leave_meter_reading' => $leaveRead,
                'is_active' => $leave === null,
                // The stay records the room's rent at the time; the FEE PLAN
                // lives on the student (one current plan, re-confirmed on
                // every move — see W6.4), not on the assignment.
                'monthly_rent' => $r->rent,
            ]);
            $r->beds[$bedIndex]->update(['status' => $leave === null ? 'occupied' : 'empty']);
        };

        // ── AC-1 · Room 501 — baseline: three occupants, full month.
        // Generate 1000 → 1100 @ ₹10 = ₹1,000 → 333.34 / 333.33 / 333.33.
        $r1 = $room('501', 3);
        foreach (['Arjun Nair', 'Bhavesh Shah', 'Chirag Patel'] as $i => $name) {
            $stay($r1, $i, $student($name), $before, null, null, null);
        }

        // ── AC-2 · Room 502 — THE OWNER'S EXAMPLE: front-loaded usage.
        // Deepak all month; Esha joins on the 16th at meter 1030.
        // Generate 1000 → 1035 @ ₹10 = ₹350. Days 1–15 burned 30u (Deepak
        // alone = ₹300); days 16–end burned 5u (shared = ₹25 each).
        // Deepak ₹325 · Esha ₹25 — NOT ₹175 each, and NOT a day-split.
        $r2 = $room('502', 2);
        $stay($r2, 0, $student('Deepak Rana'), $before, null, null, null);
        $stay($r2, 1, $student('Esha Mehta'), $mid, null, 1030.0, null);

        // ── AC-3 · Rooms 503 + 504 — THE SWAP (the hardest case).
        // Farhan starts in 503, Gita in 504; they trade rooms on the 16th.
        //   503: generate 2000 → 2100 @ ₹10 = ₹1,000; swap meter 2030.
        //        Farhan 30u = ₹300 · Gita 70u = ₹700
        //   504: generate 3000 → 3050 @ ₹10 = ₹500; swap meter 3040.
        //        Gita 40u = ₹400 · Farhan 10u = ₹100
        // Both stayed exactly half the month in each room, yet owe wildly
        // different amounts — because each room's meter says so.
        $r3 = $room('503', 2);
        $r4 = $room('504', 2);
        $farhan = $student('Farhan Qureshi');
        $gita = $student('Gita Joshi');
        $stay($r3, 0, $farhan, $before, $mid, null, 2030.0);   // 503 → out at 2030
        $stay($r4, 1, $farhan, $mid, null, 3040.0, null);      // → into 504 at 3040
        $stay($r4, 0, $gita, $before, $mid, null, 3040.0);     // 504 → out at 3040
        $stay($r3, 1, $gita, $mid, null, 2030.0, null);        // → into 503 at 2030

        // ── AC-4 · Room 505 — NO reading (legacy/skipped): day-estimate.
        // Hari all month; Ishan joins on the 16th with NO meter reading.
        // Generate 1000 → 1100 @ ₹10 = ₹1,000. Nothing anchors the 16th, so
        // the split falls back to days AND says so in the note.
        $r5 = $room('505', 2);
        $stay($r5, 0, $student('Hari Menon'), $before, null, null, null);
        $stay($r5, 1, $student('Ishan Bhatt'), $mid, null, null, null);

        // ── AC-5 · Room 506 — departed + empty stretch.
        // Jaya occupies the 1st–10th then leaves (meter 4020); room sits empty
        // after. Generate 4000 → 4050 @ ₹10 = ₹500. Jaya bears ALL of it (the
        // hostel never eats an AC bill) with an empty-days note.
        $r6 = $room('506', 2);
        $stay($r6, 0, $student('Jaya Iyer'), $before, $month->copy()->day(10), null, 4020.0);
    }
}
