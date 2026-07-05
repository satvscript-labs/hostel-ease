<?php

use App\Http\Controllers\Api\AcBillController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BedController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FloorController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\MonthlyRentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentModeController;
use App\Http\Controllers\Api\PocketMoneyController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PromiseController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SemesterFeeController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VacancyController;
use App\Http\Controllers\Api\VisitorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Sanctum token auth — Flutter Hostel Admin app)
|--------------------------------------------------------------------------
| Public:  POST /api/v1/login
| Guarded: auth:sanctum + role:hostel_admin + api.tenant (active branch via
|          the optional X-Hostel-Id header).
*/

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    // Razorpay server-to-server webhook (public; verified by HMAC signature).
    Route::post('/webhooks/razorpay', [\App\Http\Controllers\Api\WebhookController::class, 'razorpay']);

    Route::middleware(['auth:sanctum', 'role:hostel_admin,manager,accountant,warden,viewer', 'api.tenant'])->group(function () {
        // Session / profile / always-available (any hostel staff)
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        // Property
        Route::middleware('access:property')->group(function () {
            Route::get('/floors', [FloorController::class, 'index']);
            Route::post('/floors', [FloorController::class, 'store']);
            Route::put('/floors/{floor}', [FloorController::class, 'update']);
            Route::delete('/floors/{floor}', [FloorController::class, 'destroy']);
            Route::get('/rooms', [RoomController::class, 'index']);
            Route::post('/rooms', [RoomController::class, 'store']);
            Route::put('/rooms/{room}', [RoomController::class, 'update']);
            Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);
            Route::get('/beds/layout', [BedController::class, 'layout']);
            Route::get('/beds/{bed}/history', [BedController::class, 'history']);
            Route::put('/beds/{bed}/status', [BedController::class, 'updateStatus']);
            Route::get('/vacancy', [VacancyController::class, 'index']);
        });

        // Students / Assignments / Search
        Route::middleware('access:students')->group(function () {
            Route::get('/search', [SearchController::class, 'index']);
            Route::get('/students', [StudentController::class, 'index']);
            Route::post('/students', [StudentController::class, 'store']);
            Route::get('/students/{student}', [StudentController::class, 'show']);
            Route::post('/students/{student}', [StudentController::class, 'update']);
            Route::put('/students/{student}', [StudentController::class, 'update']);
            Route::delete('/students/{student}', [StudentController::class, 'destroy']);
            Route::get('/students/{student}/documents', [StudentController::class, 'documents']);
            Route::post('/students/{student}/documents', [StudentController::class, 'storeDocument']);
            Route::delete('/students/{student}/documents/{document}', [StudentController::class, 'destroyDocument']);
            Route::get('/assignments', [AssignmentController::class, 'index']);
            Route::get('/assignments/options', [AssignmentController::class, 'options']);
            Route::post('/assignments/clear-all', [AssignmentController::class, 'clearAll']);
            Route::post('/assignments', [AssignmentController::class, 'store']);
            Route::post('/assignments/{assignment}/fee', [AssignmentController::class, 'updateFee']);
            Route::post('/assignments/{assignment}/release', [AssignmentController::class, 'release']);
            Route::post('/assignments/{assignment}/transfer', [AssignmentController::class, 'transfer']);

            // Self-registration link/QR + pending approvals
            Route::get('/registration-link', [RegistrationController::class, 'link']);
            Route::post('/registration-link/regenerate', [RegistrationController::class, 'regenerate']);
            Route::get('/registrations', [RegistrationController::class, 'index']);
            Route::post('/registrations/{registration}/approve', [RegistrationController::class, 'approve']);
            Route::post('/registrations/{registration}/reject', [RegistrationController::class, 'reject']);
        });

        // People — Visitors / Complaints
        Route::middleware('access:people')->group(function () {
            Route::get('/visitors', [VisitorController::class, 'index']);
            Route::post('/visitors', [VisitorController::class, 'store']);
            Route::post('/visitors/{visitor}/checkout', [VisitorController::class, 'checkout']);
            Route::delete('/visitors/{visitor}', [VisitorController::class, 'destroy']);
            Route::get('/complaints', [ComplaintController::class, 'index']);
            Route::post('/complaints', [ComplaintController::class, 'store']);
            Route::put('/complaints/{complaint}', [ComplaintController::class, 'update']);
        });

        // Staff — salary + attendance
        Route::middleware('access:staff')->group(function () {
            Route::get('/staff', [StaffController::class, 'index']);
            Route::post('/staff', [StaffController::class, 'store']);
            Route::get('/staff/attendance', [StaffController::class, 'attendanceSheet']);
            Route::post('/staff/attendance', [StaffController::class, 'saveAttendance']);
            Route::get('/staff/{staff}', [StaffController::class, 'show']);
            Route::put('/staff/{staff}', [StaffController::class, 'update']);
            Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);
            Route::post('/staff/{staff}/salary', [StaffController::class, 'paySalary']);
            Route::delete('/staff/{staff}/salary/{payment}', [StaffController::class, 'deleteSalary']);
        });

        // Finance
        Route::middleware('access:finance')->group(function () {
            Route::get('/payment-modes', [PaymentController::class, 'modes']);
            Route::get('/payment-modes/manage', [PaymentModeController::class, 'index']);
            Route::post('/payment-modes', [PaymentModeController::class, 'store']);
            Route::put('/payment-modes/{paymentMode}', [PaymentModeController::class, 'update']);
            Route::delete('/payment-modes/{paymentMode}', [PaymentModeController::class, 'destroy']);
            Route::get('/payments', [PaymentController::class, 'index']);
            Route::post('/payments', [PaymentController::class, 'store']);
            // Collect a lump amount and auto-apply it to a student's unpaid dues (oldest first).
            Route::post('/students/{student}/collect', [StudentController::class, 'collect']);
            // Promise to pay: set a future date + note on the student's unpaid dues.
            Route::post('/students/{student}/promise', [StudentController::class, 'promise']);
            Route::get('/payments/{payment}', [PaymentController::class, 'show']);
            Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt']);
            Route::get('/semester-fees', [SemesterFeeController::class, 'index']);
            Route::post('/semester-fees', [SemesterFeeController::class, 'store']);
            Route::put('/semester-fees/{semesterFee}', [SemesterFeeController::class, 'update']);
            Route::post('/semester-fees/{semesterFee}/collect', [SemesterFeeController::class, 'collect']);
            Route::delete('/semester-fees/{semesterFee}', [SemesterFeeController::class, 'destroy']);
            Route::get('/monthly-rents', [MonthlyRentController::class, 'index']);
            Route::post('/monthly-rents/generate', [MonthlyRentController::class, 'generate']);
            Route::post('/monthly-rents/{monthlyRent}/collect', [MonthlyRentController::class, 'collect']);
            Route::delete('/monthly-rents/{monthlyRent}', [MonthlyRentController::class, 'destroy']);
            Route::get('/ac-bills', [AcBillController::class, 'index']);
            Route::get('/ac-bills/create-options', [AcBillController::class, 'createOptions']);
            Route::post('/ac-bills', [AcBillController::class, 'store']);
            Route::get('/ac-bills/{acBill}', [AcBillController::class, 'show']);
            Route::post('/ac-bills/shares/{share}/collect', [AcBillController::class, 'collect']);
            Route::delete('/ac-bills/{acBill}', [AcBillController::class, 'destroy']);
            Route::get('/ledger', [LedgerController::class, 'index']);
            Route::get('/ledger/{student}', [LedgerController::class, 'show']);
            Route::get('/expenses', [ExpenseController::class, 'index']);
            Route::post('/expenses', [ExpenseController::class, 'store']);
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
            Route::put('/promise/{type}/{id}', [PromiseController::class, 'update']);
            Route::get('/pocket-money', [PocketMoneyController::class, 'index']);
            Route::get('/pocket-money/{student}', [PocketMoneyController::class, 'show']);
            Route::post('/pocket-money/{student}', [PocketMoneyController::class, 'store']);
            Route::delete('/pocket-money/{student}/tx/{transaction}', [PocketMoneyController::class, 'destroy']);
        });

        // Reports
        Route::middleware('access:reports')->group(function () {
            Route::get('/reports', [ReportController::class, 'index']);
            Route::get('/reports/{type}', [ReportController::class, 'show']);
        });

        // Backup
        Route::middleware('access:backup')->group(function () {
            Route::get('/backup', [BackupController::class, 'export']);
        });

        // Account subscription billing (owner only) — one payment covers all branches
        Route::middleware('role:hostel_admin')->group(function () {
            Route::get('/billing', [\App\Http\Controllers\Api\BillingController::class, 'show']);
            Route::post('/billing/order', [\App\Http\Controllers\Api\BillingController::class, 'createOrder']);
            Route::post('/billing/verify', [\App\Http\Controllers\Api\BillingController::class, 'verify']);
        });

        // Sub-users + roles (owner only)
        Route::middleware('access:users')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{user}', [UserController::class, 'update']);
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);
        });
    });
});
