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

    /*
    | The platform (seller) identity — the "from" on a subscription invoice the
    | Super Admin issues to a customer for their branches. Env-overridable so a
    | real GSTIN / address can land in production without a code change.
    |
    | Each key accepts the SAAS_* name as a fallback (S0 · finding F5): the
    | production env has always carried SAAS_LEGAL_ENTITY / SAAS_GST_NUMBER /
    | SAAS_SUPPORT_EMAIL / SAAS_CONTACT_ADDRESS, which nothing read — so the
    | seller address was blank and the GSTIN could never have appeared on an
    | invoice even once it existed. Reading both names fixes it without having to
    | edit the server's .env. (Same pattern as services.razorpay's key aliases.)
    */
    'company' => [
        'name' => env('HOSTELEASE_COMPANY_NAME', 'HostelEase'),
        'legal_name' => env('HOSTELEASE_COMPANY_LEGAL', env('SAAS_LEGAL_ENTITY', 'SatvScript')),
        'tagline' => env('HOSTELEASE_COMPANY_TAGLINE', 'Hostel Management Platform'),
        'address' => env('HOSTELEASE_COMPANY_ADDRESS', env('SAAS_CONTACT_ADDRESS', '')),
        'city' => env('HOSTELEASE_COMPANY_CITY', ''),
        'state' => env('HOSTELEASE_COMPANY_STATE', ''),
        'email' => env('HOSTELEASE_COMPANY_EMAIL', env('SAAS_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@hostel-ease.satvscript.com'))),
        'website' => env('HOSTELEASE_COMPANY_WEBSITE', 'hostel-ease.satvscript.com'),
        'gstin' => env('HOSTELEASE_COMPANY_GSTIN', env('SAAS_GST_NUMBER', '')),
        'invoice_prefix' => env('HOSTELEASE_INVOICE_PREFIX', 'HE'),
    ],

    /*
    | GST on the platform's own subscription billing (decision D6, 2026-10-02).
    |
    | 'inclusive' => true  — the listed price IS the final price. ₹10,000 stays
    | ₹10,000 and decomposes to taxable ₹8,474.58 + GST ₹1,525.42 at 18%. The
    | customer always sees one round number.
    |
    | Nothing is charged or shown until a GSTIN exists: while company.gstin is
    | empty the platform is not registered, so the subscription PDF is a RECEIPT,
    | not a tax invoice, and no tax line is printed. Fill the GSTIN (env
    | SAAS_GST_NUMBER / HOSTELEASE_COMPANY_GSTIN) and it promotes itself.
    | Full tax columns + CGST/SGST/IGST split land in phase S7.
    */
    'gst' => [
        'rate' => (float) env('HOSTELEASE_GST_RATE', 18),
        'inclusive' => (bool) env('HOSTELEASE_GST_INCLUSIVE', true),
        'sac_code' => env('HOSTELEASE_GST_SAC', '998314'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment links (S2) — operator-initiated online collection
    |--------------------------------------------------------------------------
    | Defaults for the Razorpay Payment Links the operator sends from Account 360.
    |
    | `expiry_days` is clamped to Razorpay's own window (at least 15 minutes, at
    | most 6 months) when the link is created. Seven days is the default because a
    | link that outlives the conversation that produced it is how a customer pays
    | for a charge everyone has forgotten about.
    |
    | `notify_*` hand the chasing to Razorpay, which sends the link and (with
    | `reminders`) follows up for free. Each channel is switched off automatically
    | when the owner has no address on file for it, so these are ceilings, not
    | promises.
    */
    'payment_links' => [
        'expiry_days' => (int) env('SAAS_LINK_EXPIRY_DAYS', 7),
        'notify_sms' => (bool) env('SAAS_LINK_NOTIFY_SMS', true),
        'notify_email' => (bool) env('SAAS_LINK_NOTIFY_EMAIL', true),
        'reminders' => (bool) env('SAAS_LINK_REMINDERS', true),
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
