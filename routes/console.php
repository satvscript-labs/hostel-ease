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
// Advance subscription lifecycle (grace/expiry) + send reminder emails, before alerts refresh.
Schedule::command('hostelease:process-subscription-lifecycle')->dailyAt('07:30');
// Refresh dashboard alerts every morning.
Schedule::command('hostelease:generate-notifications')->dailyAt('08:00');
// Nightly database backup (keeps 30 days).
Schedule::command('hostelease:backup --prune=30')->dailyAt('02:00');


Schedule::command('hostel:generate-invoices')->dailyAt('01:00');

// Presence (gate device): poll iDMS for punches + refresh device health.
// everyMinute keeps the boards fresh to ~1 min; withoutOverlapping so a slow
// poll never stacks. See _artifact/presence_module/04_integration_and_api.md §5.
Schedule::command('hostelease:presence-sync')->everyMinute()->withoutOverlapping();

// Curfew alert: every 15 min, notify wardens of students still out past curfew
// (the command self-checks each branch's time + a once-per-day dedupe).
Schedule::command('hostelease:presence-curfew-check')->everyFifteenMinutes();
