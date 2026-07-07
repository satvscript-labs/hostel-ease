<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Call Super Admin and Demo Hostel seeders to generate rich testing data
        $this->call([
            SuperAdminSeeder::class,
            DemoHostelSeeder::class,
        ]);
    }
}
