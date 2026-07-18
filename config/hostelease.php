<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hostel Ease Application Settings
    |--------------------------------------------------------------------------
    | Central configuration for the Hostel Management SaaS domain logic.
    */

    'country_code' => env('hostelease_DEFAULT_COUNTRY_CODE', '+91'),

    // Idle session timeout in minutes (enforced client + server side).
    'session_timeout' => (int) env('hostelease_SESSION_TIMEOUT', 30),

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

    // Fallback ceiling for room-sharing size until a hostel sets its own via
    // the Layout Builder's "Room Settings" (stored per-hostel in hostels.settings).
    'default_max_room_sharing' => 7,

    // Hard sanity cap on what a hostel can set that ceiling to.
    'max_room_sharing_limit' => 30,

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

    'subscription_amount' => (float) env('hostelease_SUBSCRIPTION_AMOUNT', 5000),

    /*
    | The platform (seller) identity — the "from" on a subscription invoice the
    | Super Admin issues to a customer for their branches. Env-overridable so a
    | real GSTIN / address can land in production without a code change.
    */
    'company' => [
        'name' => env('HOSTELEASE_COMPANY_NAME', 'HostelEase'),
        'legal_name' => env('HOSTELEASE_COMPANY_LEGAL', 'SatvScript'),
        'tagline' => env('HOSTELEASE_COMPANY_TAGLINE', 'Hostel Management Platform'),
        'address' => env('HOSTELEASE_COMPANY_ADDRESS', ''),
        'city' => env('HOSTELEASE_COMPANY_CITY', ''),
        'state' => env('HOSTELEASE_COMPANY_STATE', ''),
        'email' => env('HOSTELEASE_COMPANY_EMAIL', env('MAIL_FROM_ADDRESS', 'support@hostel-ease.satvscript.com')),
        'website' => env('HOSTELEASE_COMPANY_WEBSITE', 'hostel-ease.satvscript.com'),
        'gstin' => env('HOSTELEASE_COMPANY_GSTIN', ''),
        'invoice_prefix' => env('HOSTELEASE_INVOICE_PREFIX', 'HE'),
    ],

    /*
    | Branch-level subscription pricing.
    | Billing is handled individually per branch.
    */
    'subscription_pricing' => [
        'yearly' => (float) env('hostelease_PRICE_YEARLY', 10000),
        'monthly' => (float) env('hostelease_PRICE_MONTHLY', 1000),
    ],

    // Free trial length (days) for a new account (per-account, BRD D5).
    'trial_days' => (int) env('hostelease_TRIAL_DAYS', 14),

    // Grace window (days) after the anchor date before access is hard-blocked (BR-18).
    'grace_days' => (int) env('hostelease_GRACE_DAYS', 3),

    // How manual + volume discounts combine: 'stack' (sequential) or 'greater' (best of the two).
    'discount_stacking' => env('hostelease_DISCOUNT_STACKING', 'stack'),

    /*
    | Production lock (P4 item 15): owner self-serve billing operations — online
    | renewals, add-branch payments, and self-serve branch creation. While false,
    | owners can SEE their plans/coverage but every mutating billing op is
    | supervised: they must go through the Super Admin (Account 360). Flip the
    | env once online payments are launched.
    */
    'owner_self_serve' => (bool) env('HOSTELEASE_OWNER_SELF_SERVE', false),

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

