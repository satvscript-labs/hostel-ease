<?php

use App\Http\Controllers\Api\AuthController;
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
});
