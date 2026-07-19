<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $mobile = substr(preg_replace('/\D+/', '', env('SUPERADMIN_MOBILE', '8140740705')), -10);
        $mobile = '+91' . $mobile;

        User::updateOrCreate(
            ['mobile' => $mobile],
            [
                'hostel_id' => null,
                'name' => env('SUPERADMIN_NAME', 'Super Admin'),
                'email' => env('SUPERADMIN_EMAIL', 'admin@satvscript.com'),
                'password' => Hash::make(env('SUPERADMIN_PASSWORD', 'ChangeMe@123')),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        $this->command->info("Super Admin ready — login mobile: {$mobile}");
    }
}
