<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Remove every row the billing lab made (BillingLabSeeder) — and nothing else.
 *
 *     php artisan db:seed --class=BillingLabPurgeSeeder
 */
class BillingLabPurgeSeeder extends Seeder
{
    public function run(): void
    {
        $count = BillingLabSeeder::labUsers()->count();

        DB::transaction(fn () => BillingLabSeeder::purge());

        $this->command?->info("Billing lab removed ({$count} lab logins and everything they owned).");
    }
}
