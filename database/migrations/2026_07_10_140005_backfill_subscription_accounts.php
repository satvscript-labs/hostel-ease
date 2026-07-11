<?php

use App\Services\Billing\AccountBackfillService;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 2 — populate the account spine from existing per-branch data.
 * Delegates to AccountBackfillService (idempotent + unit-tested). On a fresh
 * database (e.g. test setup) this is a no-op because there is no data yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(AccountBackfillService::class)->run();
    }

    public function down(): void
    {
        // The account spine is derived data; clearing it is the reverse.
        // (Table drops happen in the individual create-table migrations' down().)
        \App\Models\SubscriptionOrderLine::query()->delete();
        \App\Models\SubscriptionOrder::query()->forceDelete();
        \App\Models\SubscriptionAccount::query()->forceDelete();
    }
};
