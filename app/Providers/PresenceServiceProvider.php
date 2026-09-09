<?php

namespace App\Providers;

use App\Services\Presence\FakePresenceAdapter;
use App\Services\Presence\PresenceDeviceAdapter;
use App\Services\Presence\TimeWatchIdmsAdapter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the presence adapter seam to the configured driver. Tests override this
 * with `$this->app->instance(PresenceDeviceAdapter::class, $fake)` to script the
 * whole pipeline without hardware.
 */
class PresenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton so `driver=fake` local dev keeps its scripted state across
        // resolves (tests bind their own instance regardless).
        $this->app->singleton(FakePresenceAdapter::class);

        $this->app->bind(PresenceDeviceAdapter::class, function ($app) {
            $cfg = config('presence.timewatch');

            return match (config('presence.driver')) {
                'timewatch' => new TimeWatchIdmsAdapter(
                    baseUrl: $cfg['base_url'] ?? null,
                    apiKey: $cfg['api_key'] ?? null,
                    timeout: (int) ($cfg['timeout'] ?? 15),
                    retries: (int) ($cfg['retries'] ?? 2),
                ),
                // 'connector' resolves to the real ConnectorAdapter in S3. Until
                // then it has no OUTBOUND path (enrollment/health), so it falls
                // back to the fake rather than silently constructing an iDMS
                // client that would call a server we no longer run. The INBOUND
                // punch feed does not use this seam at all — the webhook calls
                // PresenceService::ingest() directly.
                default => $app->make(FakePresenceAdapter::class),
            };
        });
    }
}
