<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PresenceDevice;
use App\Services\Presence\PresenceService;
use App\Services\Presence\SdkEventMapper;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Presence S1 — the inbound gate-event endpoint (10 §8/§9.3).
 *
 * The Presence Connector (a Windows service speaking the device SDK) POSTs each
 * scan here in near real time, and flushes its offline buffer here in batches
 * after an outage.
 *
 * Security posture — this is our FIRST public device endpoint, so it is treated
 * as untrusted (04 §6):
 *   - authenticity by HMAC-SHA256 over the raw body (shared secret, timing-safe
 *     compare) — there is no session and no user;
 *   - only serials that already exist as devices are accepted, so a valid
 *     signature still cannot invent hardware;
 *   - batch size capped, route throttled, body implicitly bounded by the cap;
 *   - replay-safe by construction: the punch unique index
 *     (device, device_user_id, punched_at) makes a re-delivered batch a no-op,
 *     which is exactly what lets the Connector retry fearlessly.
 *
 * Runs OUTSIDE any tenant context on purpose — like the sync command. Each
 * punch's hostel is resolved from its device row, so one Connector can serve
 * devices across several branches.
 */
class PresenceEventController extends Controller
{
    public function __construct(
        protected PresenceService $presence,
        protected SdkEventMapper $mapper,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $raw = $request->getContent();

        if (! $this->signatureValid($raw, (string) $request->header('X-Presence-Signature', ''))) {
            // Deliberately vague: a probe learns nothing about why it failed.
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Malformed JSON'], 422);
        }

        // Accept either a single event or {"events": [...]} so the Connector can
        // use one code path for live pushes and buffered flushes.
        $events = $payload['events'] ?? [$payload];
        if (! is_array($events) || $events === []) {
            return response()->json(['message' => 'No events'], 422);
        }

        $max = (int) config('presence.connector.max_batch', 200);
        if (count($events) > $max) {
            return response()->json(['message' => "Batch too large (max {$max})"], 422);
        }

        return response()->json($this->ingest($events));
    }

    /**
     * Map → filter → ingest. Returns a per-batch report so the Connector can log
     * precisely what we took and clear only those from its buffer.
     *
     * @param  array<int, mixed>  $events
     * @return array<string, mixed>
     */
    protected function ingest(array $events): array
    {
        // Never inherit a tenant here: device→hostel resolution must span branches.
        Tenant::clear();

        $known = PresenceDevice::query()->pluck('serial_number')->all();

        $punches = collect();
        $rejected = [];

        foreach ($events as $i => $event) {
            if (! is_array($event)) {
                $rejected[] = ['index' => $i, 'reason' => 'not_an_object'];

                continue;
            }

            $punch = $this->mapper->map($event);
            if (! $punch) {
                $rejected[] = ['index' => $i, 'reason' => 'unmappable'];

                continue;
            }

            // A signed caller still cannot introduce a device we don't know.
            if (! in_array($punch->deviceSerial, $known, true)) {
                $rejected[] = ['index' => $i, 'reason' => 'unknown_device', 'serial' => $punch->deviceSerial];

                continue;
            }

            // A wildly wrong clock corrupts every duration downstream — refuse it
            // loudly here rather than storing a punch we'd have to unpick later.
            if (! $this->mapper->isPlausible($punch->punchedAt)) {
                $rejected[] = ['index' => $i, 'reason' => 'implausible_time', 'punched_at' => $punch->punchedAt->toIso8601String()];

                continue;
            }

            if (! $this->mapper->shouldStore($event)) {
                $rejected[] = ['index' => $i, 'reason' => 'filtered'];

                continue;
            }

            $punches->push($punch);
        }

        $stored = $punches->isEmpty() ? 0 : $this->presence->ingest($punches);

        if ($rejected !== []) {
            Log::warning('Presence webhook: some events rejected', [
                'received' => count($events), 'stored' => $stored, 'rejected' => $rejected,
            ]);
        }

        return [
            'received' => count($events),
            'accepted' => $punches->count(),
            'stored' => $stored,          // < accepted when a punch was a duplicate
            'rejected' => $rejected,
        ];
    }

    /**
     * HMAC-SHA256 of the raw body, compared in constant time. A missing secret
     * fails closed — an unconfigured server must never accept unsigned punches.
     */
    protected function signatureValid(string $raw, string $provided): bool
    {
        $secret = (string) config('presence.connector.secret', '');

        if ($secret === '' || $provided === '') {
            return false;
        }

        // Tolerate the common "sha256=..." prefix as well as a bare hex digest.
        $provided = str_starts_with($provided, 'sha256=') ? substr($provided, 7) : $provided;

        return hash_equals(hash_hmac('sha256', $raw, $secret), $provided);
    }
}
