<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PresenceEventController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| The authenticated mobile-app API (property/students/finance/staff/...)
| that used to live here was built for an old Flutter app that was never
| shipped and predates the unified Invoice model. It has been removed
| rather than patched — a new mobile app will be designed and built
| against the current web data model from scratch.
|
| What remains is genuinely live infrastructure, independent of any
| mobile app:
|   - POST /login   — kept for reuse when the new mobile app is built.
|   - POST /webhooks/razorpay — called directly by Razorpay's servers to
|     confirm subscription payments; see App\Http\Controllers\Api\WebhookController.
*/

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    // Razorpay server-to-server webhook (public; verified by HMAC signature).
    Route::post('/webhooks/razorpay', [WebhookController::class, 'razorpay']);

    // Presence gate events from the Connector (public; verified by HMAC
    // signature + device allow-list). Throttled generously: a busy gate is a
    // few events a minute, but a reconnecting Connector flushes its buffer in
    // batches, so the limit guards abuse without punishing recovery.
    Route::post('/presence/events', [PresenceEventController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('api.presence.events');
});
