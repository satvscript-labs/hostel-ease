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
    // Which adapter backs PresenceService. 'timewatch' = the iDMS HTTP adapter;
    // 'fake' = the in-memory scriptable adapter (tests, local dev without a box).
    'driver' => env('PRESENCE_DRIVER', 'timewatch'),

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

    // device_user_id scheme: prefix encodes audience so ingest resolves it
    // instantly; the numeric part binds the model (04 §3). S412 / T18.
    'user_id_prefixes' => [
        'student' => 'S',
        'staff' => 'T',
    ],
];
