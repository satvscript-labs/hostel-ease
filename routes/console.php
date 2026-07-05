<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| Renewal reminders, due-fee notifications and vacancy alerts run daily.
| Commands are registered as their modules are built.
*/
// Auto-generate monthly rent rows on the 1st of each month.
Schedule::command('hsms:generate-monthly-rents')->monthlyOn(1, '00:30');
// Refresh dashboard alerts every morning.
Schedule::command('hsms:generate-notifications')->dailyAt('08:00');
// Nightly database backup (keeps 30 days).
Schedule::command('hsms:backup --prune=30')->dailyAt('02:00');
