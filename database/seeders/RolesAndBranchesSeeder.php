<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndBranchesSeeder extends Seeder
{
    public function run(): void
    {
        // Create default roles
        Role::firstOrCreate(['name' => 'super_admin'], [
            'display_name' => 'Super Admin',
            'description' => 'Full system access across all hostels',
            'permissions' => ['*'],
        ]);

        Role::firstOrCreate(['name' => 'hostel_admin'], [
            'display_name' => 'Hostel Admin',
            'description' => 'Full control over assigned hostel/branch',
            'permissions' => ['users.manage', 'students.manage', 'fees.manage', 'reports.view', 'settings.manage'],
        ]);

        Role::firstOrCreate(['name' => 'manager'], [
            'display_name' => 'Manager',
            'description' => 'Can manage students, fees, and collect payments',
            'permissions' => ['students.manage', 'fees.manage', 'payments.collect', 'reports.view'],
        ]);

        Role::firstOrCreate(['name' => 'accountant'], [
            'display_name' => 'Accountant',
            'description' => 'Can view and collect payments, access financial reports',
            'permissions' => ['payments.collect', 'payments.view', 'reports.view'],
        ]);

        Role::firstOrCreate(['name' => 'staff'], [
            'display_name' => 'Staff',
            'description' => 'Can view students and submit reports',
            'permissions' => ['students.view', 'reports.submit'],
        ]);

        // Create default branch for each hostel (if hostels exist)
        $hostels = \App\Models\Hostel::all();
        foreach ($hostels as $hostel) {
            Branch::firstOrCreate(
                ['hostel_id' => $hostel->id, 'name' => 'Main Branch'],
                ['code' => strtoupper(substr($hostel->name, 0, 3)) . '-001', 'location' => $hostel->address ?? null, 'is_active' => true]
            );
        }
    }
}
