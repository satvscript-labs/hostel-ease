<?php

namespace App\Console\Commands;

use App\Services\MonthlyRentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMonthlyRents extends Command
{
    protected $signature = 'hostelease:generate-monthly-rents {--month= : Month in YYYY-MM (defaults to current)}';

    protected $description = 'Generate monthly rent rows for all active working professionals across every hostel.';

    public function handle(MonthlyRentService $service): int
    {
        $month = $this->option('month')
            ? Carbon::parse($this->option('month').'-01')
            : now()->startOfMonth();

        // No tenant bound in console → service runs across all hostels.
        $created = $service->generateForMonth($month);

        $this->info("Generated {$created} monthly rent row(s) for {$month->format('M Y')}.");

        return self::SUCCESS;
    }
}

