<?php

namespace App\Console\Commands;

use App\Models\Hostel;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class GenerateNotifications extends Command
{
    protected $signature = 'hsms:generate-notifications';

    protected $description = 'Generate dashboard alerts (renewals, fees, AC, leaving, document expiry) for all hostels.';

    public function handle(NotificationService $service): int
    {
        $service->generateForSuperAdmin();

        Hostel::query()->each(fn (Hostel $hostel) => $service->generateForHostel($hostel));

        $this->info('Notifications generated.');

        return self::SUCCESS;
    }
}
