<?php

/*
|--------------------------------------------------------------------------
| Presence module — gate device integration
|--------------------------------------------------------------------------
| Config over magic strings (development_standards §1). The device never talks
| to us directly: we poll the vendor's iDMS middleware. See
| _artifact/presence_module/04_integration_and_api.md.
|
| ⚠ The key printed in the vendor PDF (T!meW@tch#123@) is a shared default —
| the vendor must change it before go-live. Never commit a real key; use env.
*/

return [
    // Which adapter backs PresenceService.
    //   'connector' = the Presence Connector (Dahua NetSDK bridge) — CURRENT
    //   'fake'      = in-memory scriptable adapter (tests, local dev, CI)
    //   'timewatch' = the retired iDMS HTTP adapter (kept, see 04 §0)
    'driver' => env('PRESENCE_DRIVER', 'fake'),

    /*
    | The Connector — a small Windows service that speaks the device's SDK on
    | one side and plain HTTP to us on the other (10 §8). It POSTs each gate
    | event to /api/v1/presence/events.
    |
    | Punches are real hardware truth, so the endpoint is treated as public and
    | untrusted: a shared secret proves the caller, and the punch unique index
    | makes replays free.
    */
    'connector' => [
        // Shared secret the Connector sends in the X-Presence-Signature header
        // (HMAC-SHA256 of the raw body). NEVER commit a real value.
        'secret' => env('PRESENCE_CONNECTOR_SECRET'),

        // Reject events whose timestamp is absurd — a device with a wildly wrong
        // clock (they ship on UTC+08:00) would otherwise poison every duration.
        'max_future_minutes' => (int) env('PRESENCE_MAX_FUTURE_MINUTES', 10),
        'max_age_days' => (int) env('PRESENCE_MAX_AGE_DAYS', 30),

        // Largest batch a single POST may carry (the Connector buffers offline
        // and flushes in batches on reconnect).
        'max_batch' => (int) env('PRESENCE_MAX_BATCH', 200),
    ],

    'timewatch' => [
        // e.g. http://idms-host:8001/TimeWatchAPI  (no trailing slash)
        'base_url' => env('PRESENCE_IDMS_URL'),
        'api_key' => env('PRESENCE_IDMS_KEY'),
        'timeout' => (int) env('PRESENCE_IDMS_TIMEOUT', 15),
        'retries' => (int) env('PRESENCE_IDMS_RETRIES', 2),
    ],

    'sync' => [
        // Baseline look-back for GetPunchData when we have no last-success marker.
        'window_minutes' => (int) env('PRESENCE_SYNC_WINDOW', 15),
        // Re-read overlap so a killed/late run back-fills with no double counting
        // (the punch unique index makes the overlap free).
        'overlap_minutes' => (int) env('PRESENCE_SYNC_OVERLAP', 10),
        // Two punches by one person inside this window collapse — a fumbled
        // double-scan must never invert reality (01 §4).
        'debounce_seconds' => (int) env('PRESENCE_DEBOUNCE', 60),
        // A profile still "inside" longer than this is flagged stale — the
        // always-works half of the missed-punch detector (01 §4).
        'stale_hours' => (int) env('PRESENCE_STALE_HOURS', 24),
    ],

    // Minutes after the branch curfew before the warden is alerted (03 §6) —
    // a grace window so a couple of stragglers don't fire it instantly.
    'curfew_grace_minutes' => (int) env('PRESENCE_CURFEW_GRACE', 30),

    // device_user_id scheme: prefix encodes audience so ingest resolves it
    // instantly; the numeric part binds the model (04 §3). S412 / T18.
    'user_id_prefixes' => [
        'student' => 'S',
        'staff' => 'T',
    ],
];
