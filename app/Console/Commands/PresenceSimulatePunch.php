<?php

namespace App\Console\Commands;

use App\Models\PresenceDevice;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Presence S1 — hand-test helper. Signs and POSTs a realistic gate event to our
 * own ingest endpoint, exactly as the Connector will (10 §8).
 *
 * Exists because the endpoint is HMAC-signed: computing that by hand (or wiring
 * a pre-request script in a REST client) is fiddly enough to discourage testing,
 * and an untested ingest path is the last thing we want. Also doubles as the
 * driver for developing S2 before any Connector exists.
 *
 * Writes a REAL punch — it is a test tool, not a toy: guarded in production.
 */
class PresenceSimulatePunch extends Command
{
    protected $signature = 'presence:simulate-punch
        {--serial= : Device serial (default: first active device)}
        {--user= : device_user_id, e.g. S412 (default: first enrolled profile)}
        {--state=1 : emAttendanceState — 1 SignIn, 2 GoOut, 3 BreakIn, 4 SignOut, 5 OT-In, 6 OT-Out}
        {--method=16 : emOpenMethod — 16 Face, 2 Card, 6 Fingerprint, 1 Password}
        {--at= : UTC time (default: now). Accepts "2026-07-25T16:17:03Z" or "-2 hours"}
        {--url= : Endpoint (default: APP_URL + /api/v1/presence/events)}
        {--raw : Print the signed payload and curl equivalent, do not send}';

    protected $description = 'Sign and POST a simulated gate punch to the presence ingest endpoint (hand-test).';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->confirm('This writes a REAL punch in PRODUCTION. Continue?', false)) {
            return self::FAILURE;
        }

        $secret = (string) config('presence.connector.secret', '');
        if ($secret === '') {
            $this->error('PRESENCE_CONNECTOR_SECRET is not set — the endpoint fails closed without it.');
            $this->line('  Generate one:  php -r "echo bin2hex(random_bytes(32));"');
            $this->line('  Add to .env:   PRESENCE_CONNECTOR_SECRET=...   then: php artisan config:clear');

            return self::FAILURE;
        }

        // Devices/profiles are tenant-scoped; the lookup below is a convenience
        // for whoever runs this, so search across branches like the Connector.
        Tenant::clear();

        $serial = $this->option('serial') ?: PresenceDevice::query()->where('is_active', true)->value('serial_number');
        if (! $serial) {
            $this->error('No active device found — add one under Presence → Devices, or pass --serial.');

            return self::FAILURE;
        }

        // Auto-pick someone the BOARD will actually show: a profile outside the
        // enrolled scope (failed/removed) is hidden there, so punching as them
        // looks like "nothing happened" and wastes a debugging hour.
        $user = $this->option('user') ?: \App\Models\PresenceProfile::query()->enrolled()->value('device_user_id');
        if (! $user) {
            $this->error('No enrolled profile found — enroll someone, or pass --user (e.g. --user=S412).');

            return self::FAILURE;
        }

        // If the caller named someone explicitly, warn when they're board-hidden.
        $profile = \App\Models\PresenceProfile::query()->where('device_user_id', $user)->first();
        if ($profile && ! in_array($profile->enrollment_status->value, ['pending', 'active'], true)) {
            $this->warn("  Note: {$user} has enrollment_status = {$profile->enrollment_status->value},");
            $this->warn('  so the board hides them. The punch will still be stored.');
        }

        $at = $this->option('at')
            ? \Illuminate\Support\Carbon::parse($this->option('at'))->utc()
            : now()->utc();

        $body = json_encode([
            'device_serial' => $serial,
            'device_user_id' => $user,
            'punched_at_utc' => $at->toIso8601String(),
            'attendance_state' => (int) $this->option('state'),
            'open_method' => (int) $this->option('method'),
            'granted' => true,
        ]);

        $signature = hash_hmac('sha256', $body, $secret);
        $url = $this->option('url') ?: rtrim((string) config('app.url'), '/').'/api/v1/presence/events';

        $localTime = $at->copy()->setTimezone(config('app.timezone'))->format('d M Y H:i:s');
        $this->line('');
        $this->line("  <fg=gray>device</>   {$serial}");
        $this->line("  <fg=gray>person</>   {$user}");
        $this->line("  <fg=gray>utc</>      {$at->toIso8601String()}");
        $this->line("  <fg=gray>local</>    {$localTime}  <fg=gray>(".config('app.timezone').')</>');
        $this->line("  <fg=gray>state</>    {$this->option('state')}  <fg=gray>→ ".$this->stateLabel().'</>');
        $this->line('');

        if ($this->option('raw')) {
            $this->line($body);
            $this->line('');
            $this->line("curl -X POST {$url} \\");
            $this->line("  -H 'Content-Type: application/json' \\");
            $this->line("  -H 'X-Presence-Signature: {$signature}' \\");
            $this->line("  -d '{$body}'");

            return self::SUCCESS;
        }

        try {
            $res = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Presence-Signature' => $signature,
            ])->withoutVerifying()->withBody($body, 'application/json')->post($url);
        } catch (\Throwable $e) {
            $this->error('Request failed: '.$e->getMessage());
            $this->line("  Is {$url} reachable from here?");

            return self::FAILURE;
        }

        $this->line("  <fg=gray>POST</> {$url}  <fg=gray>→</> {$res->status()}");
        $this->line('  '.$res->body());
        $this->line('');

        if ($res->successful()) {
            $json = $res->json() ?? [];
            if (($json['stored'] ?? 0) > 0) {
                $this->info('  ✔ Stored. Open Presence → Students and check the board.');
            } elseif (($json['accepted'] ?? 0) > 0) {
                $this->warn('  Accepted but not stored — an identical punch already exists (idempotent).');
            } else {
                $this->warn('  Nothing stored — see "rejected" above for why.');
            }

            return self::SUCCESS;
        }

        if ($res->status() === 401) {
            $this->error('  401 — the secret here does not match the one the app is running with.');
            $this->line('  Run: php artisan config:clear');
        }

        return self::FAILURE;
    }

    protected function stateLabel(): string
    {
        return match ((int) $this->option('state')) {
            1 => 'Check In → INSIDE',
            2 => 'Break Out → OUT',
            3 => 'Break In → INSIDE',
            4 => 'Check Out → OUT',
            5 => 'Overtime In → INSIDE',
            6 => 'Overtime Out → OUT',
            default => 'Unknown → falls back to the device direction mode',
        };
    }
}
