<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HSMS Application Settings
    |--------------------------------------------------------------------------
    | Central configuration for the Hostel Management SaaS domain logic.
    */

    'country_code' => env('HSMS_DEFAULT_COUNTRY_CODE', '+91'),

    // Idle session timeout in minutes (enforced client + server side).
    'session_timeout' => (int) env('HSMS_SESSION_TIMEOUT', 30),

    'roles' => [
        'super_admin' => 'Super Admin',
        'hostel_admin' => 'Hostel Admin',
    ],

    // Sub-user roles a hostel owner (hostel_admin) can assign to staff logins.
    'staff_roles' => [
        'manager' => 'Manager',
        'accountant' => 'Accountant',
        'warden' => 'Warden',
        'viewer' => 'Viewer (read-only)',
    ],

    /*
    | Access matrix: which feature "areas" each role can use, and whether their
    | access is read-only. 'hostel_admin' (the owner) has full access. Areas:
    | property, students, people, staff, finance, reports, backup, users.
    */
    'role_access' => [
        'hostel_admin' => ['areas' => ['*'], 'readonly' => false],
        'manager' => ['areas' => ['property', 'students', 'people', 'staff', 'finance', 'reports', 'backup'], 'readonly' => false],
        'accountant' => ['areas' => ['finance', 'reports', 'students'], 'readonly' => false],
        'warden' => ['areas' => ['property', 'students', 'people', 'staff', 'reports'], 'readonly' => false],
        'viewer' => ['areas' => ['*'], 'readonly' => true],
    ],

    'hostel_status' => [
        'active' => 'Active',
        'expired' => 'Expired',
        'suspended' => 'Suspended',
    ],

    'room_types' => [
        'ac' => 'AC',
        'non_ac' => 'Non AC',
    ],

    'sharing_options' => [1, 2, 3, 4, 5, 6, 7],

    'bed_statuses' => [
        'empty' => ['label' => 'Empty', 'color' => '#22c55e'],
        'occupied' => ['label' => 'Occupied', 'color' => '#ef4444'],
        'reserved' => ['label' => 'Reserved', 'color' => '#eab308'],
        'maintenance' => ['label' => 'Maintenance', 'color' => '#9ca3af'],
    ],

    'occupation_types' => [
        'student' => 'Student',
        'working' => 'Working Professional',
    ],

    'payment_modes' => [
        'cash' => 'Cash',
        'upi' => 'UPI',
        'cheque' => 'Cheque',
        'rtgs' => 'RTGS',
    ],

    'payment_types' => [
        'full' => 'Full Payment',
        'partial' => 'Partial Payment',
        'advance' => 'Advance Payment',
    ],

    'payment_statuses' => [
        'paid' => 'Paid',
        'partial' => 'Partial',
        'pending' => 'Pending',
        'failed' => 'Failed',
    ],

    'semesters' => [1, 2, 3, 4, 5, 6, 7, 8],

    // Fee collection frequency chosen per student at bed assignment.
    'fee_frequencies' => [
        'monthly' => 'Monthly',
        'semester' => 'Semester',
        'yearly' => 'Yearly',
    ],

    // Subscription expiry reminder windows (days before end date).
    'renewal_reminder_days' => [30, 15, 7, 0],

    // Vacancy lookahead windows (days).
    'vacancy_windows' => [7, 15, 30],

    'subscription_amount' => (float) env('HSMS_SUBSCRIPTION_AMOUNT', 5000),

    /*
    | Account-level subscription pricing. Billing is per OWNER (account), not
    | per branch payment: one payment covers every branch the owner holds.
    | Discount rule: for every `free_per` branches, one branch is free — i.e.
    | free branches = floor(total / free_per), payable = total - free.
    */
    'subscription_pricing' => [
        'yearly' => (float) env('HSMS_PRICE_YEARLY', 10000),
        'monthly' => (float) env('HSMS_PRICE_MONTHLY', 1000),
        'free_per' => (int) env('HSMS_FREE_PER_BRANCHES', 3),
    ],

    // Path to the mysqldump binary (XAMPP: D:\xampp\mysql\bin\mysqldump.exe).
    'dump_binary' => env('DB_DUMP_BINARY', 'mysqldump'),

    'expense_categories' => [
        'electricity' => 'Electricity',
        'water' => 'Water',
        'staff_salary' => 'Staff Salary',
        'maintenance' => 'Maintenance',
        'groceries' => 'Groceries',
        'rent' => 'Rent',
        'other' => 'Other',
    ],

    'complaint_categories' => [
        'maintenance' => 'Maintenance',
        'electricity' => 'Electricity',
        'plumbing' => 'Plumbing',
        'cleanliness' => 'Cleanliness',
        'food' => 'Food',
        'wifi' => 'Wi-Fi / Internet',
        'security' => 'Security',
        'other' => 'Other',
    ],

    'complaint_priorities' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],

    'complaint_statuses' => [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ],

];
