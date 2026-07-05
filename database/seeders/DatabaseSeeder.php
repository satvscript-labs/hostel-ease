<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Production-clean: only the Super Admin account.
        // (DemoHostelSeeder / ShraddhaSeeder remain available to call manually
        //  for local demos: `php artisan db:seed --class=DemoHostelSeeder`.)
        $this->call([
            SuperAdminSeeder::class,
        ]);
    }
}
