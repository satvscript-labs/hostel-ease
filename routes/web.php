<?php

use App\Http\Controllers\Admin\AcBillController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\BedController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FloorController;
use App\Http\Controllers\Admin\LedgerController;
use App\Http\Controllers\Admin\MonthlyRentController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentModeController;
use App\Http\Controllers\Admin\PromiseController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\SemesterFeeController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentDocumentController;
use App\Http\Controllers\Admin\VacancyController;
use App\Http\Controllers\Admin\VisitorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SuperAdmin\AdminController;
use App\Http\Controllers\SuperAdmin\BackupController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\SuperAdmin\HostelController;
use App\Http\Controllers\SuperAdmin\SubscriptionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public / Guest
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => Auth::check() ? redirect()->route('dashboard') : redirect()->route('login'));

// Language switcher (available to everyone, incl. the login page).
Route::get('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// One-time web installer (token-guarded; 404 when SETUP_TOKEN is blank).
Route::get('__install/{token}', [SetupController::class, 'install'])->name('setup.install');

// Public student self-registration form (per-hostel token/QR link).
Route::get('register/{token}', [\App\Http\Controllers\PublicRegistrationController::class, 'show'])->name('public.register');
Route::post('register/{token}', [\App\Http\Controllers\PublicRegistrationController::class, 'submit'])->middleware('throttle:15,1');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated (tenant-aware)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::view('subscription/expired', 'subscription.expired')->name('subscription.expired');

    // Notifications (both roles)
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Global instant search (both roles)
    Route::get('search', [SearchController::class, 'index'])->name('search');

    // Change password (both roles)
    Route::get('profile/password', [ProfileController::class, 'edit'])->name('profile.password');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Switch active branch (multi-branch hostel admins)
    Route::get('branch/{hostel}/switch', [BranchController::class, 'switch'])->name('branch.switch');

    // Account billing / renewal (hostel owner). Deliberately OUTSIDE the
    // subscription.active gate so an expired owner can still reach the pay page.
    Route::middleware('role:hostel_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('billing', [\App\Http\Controllers\Admin\BillingController::class, 'show'])->name('billing');
        Route::post('billing/order', [\App\Http\Controllers\Admin\BillingController::class, 'createOrder'])->name('billing.order');
        Route::post('billing/verify', [\App\Http\Controllers\Admin\BillingController::class, 'verify'])->name('billing.verify');
    });

    /*
    |----------------------------------------------------------------------
    | Super Admin area
    |----------------------------------------------------------------------
    */
    Route::middleware('role:super_admin')->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('dashboard', [SuperAdminDashboard::class, 'index'])->name('dashboard');

        // --- Module 12: Hostels, Subscriptions, Admins ---
        Route::resource('hostels', HostelController::class);

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::put('subscriptions/{subscription}', [SubscriptionController::class, 'update'])->name('subscriptions.update');
        Route::patch('subscriptions/{subscription}/accept', [SubscriptionController::class, 'accept'])->name('subscriptions.accept');
        Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');

        Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
        Route::post('admins', [AdminController::class, 'store'])->name('admins.store');
        Route::patch('admins/{admin}/toggle', [AdminController::class, 'toggle'])->name('admins.toggle');
        Route::patch('admins/{admin}/reset-password', [AdminController::class, 'resetPassword'])->name('admins.reset');
        Route::put('admins/{admin}/branches', [AdminController::class, 'branches'])->name('admins.branches');

        Route::get('activity', [AdminController::class, 'activity'])->name('activity');

        // --- Add-on: Database Backups ---
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Hostel Admin area (requires an active subscription)
    |----------------------------------------------------------------------
    */
    Route::middleware(['role:hostel_admin', 'subscription.active'])
        ->prefix('admin')->name('admin.')->group(function () {
            Route::get('dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

            // --- Module 1: Property Board ---
            Route::get('property', [\App\Http\Controllers\Admin\PropertyController::class, 'index'])->name('property.index');
            Route::resource('floors', FloorController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('rooms', RoomController::class)->except(['show']);
            Route::get('beds/{bed}/history', [BedController::class, 'history'])->name('beds.history');
            Route::patch('beds/{bed}/status', [BedController::class, 'updateStatus'])->name('beds.status');

            // --- Module 2: Students ---
            Route::resource('students', StudentController::class);
            Route::post('students/{student}/collect', [StudentController::class, 'collect'])->name('students.collect');
            Route::post('students/{student}/promise', [StudentController::class, 'promise'])->name('students.promise');
            Route::post('students/{student}/documents', [StudentDocumentController::class, 'store'])
                ->name('students.documents.store');
            Route::delete('students/{student}/documents/{document}', [StudentDocumentController::class, 'destroy'])
                ->name('students.documents.destroy');

            // --- Module 3: Bed Assignment ---
            Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
            Route::get('assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
            Route::post('assignments', [AssignmentController::class, 'store'])->name('assignments.store');
            Route::patch('assignments/{assignment}/fee', [AssignmentController::class, 'updateFee'])->name('assignments.fee');
            Route::patch('assignments/{assignment}/release', [AssignmentController::class, 'release'])->name('assignments.release');
            Route::patch('assignments/{assignment}/transfer', [AssignmentController::class, 'transfer'])->name('assignments.transfer');


            // --- Module 5: Fees & Receipts ---
            Route::resource('payments', PaymentController::class)
                ->only(['index', 'create', 'store', 'show', 'destroy']);
            Route::get('payments/{payment}/pdf', [PaymentController::class, 'pdf'])->name('payments.pdf');
            Route::post('payments/{payment}/whatsapp', [PaymentController::class, 'whatsapp'])->name('payments.whatsapp');
            Route::post('payments/{payment}/email', [PaymentController::class, 'email'])->name('payments.email');

            // --- Module 6: Semester Fees ---
            Route::get('semester-fees', [SemesterFeeController::class, 'index'])->name('semester-fees.index');
            Route::post('semester-fees', [SemesterFeeController::class, 'store'])->name('semester-fees.store');
            Route::put('semester-fees/{semester_fee}', [SemesterFeeController::class, 'update'])->name('semester-fees.update');
            Route::post('semester-fees/{semester_fee}/collect', [SemesterFeeController::class, 'collect'])->name('semester-fees.collect');
            Route::delete('semester-fees/{semester_fee}', [SemesterFeeController::class, 'destroy'])->name('semester-fees.destroy');

            // --- Module 6: Monthly Rent ---
            Route::get('monthly-rents', [MonthlyRentController::class, 'index'])->name('monthly-rents.index');
            Route::post('monthly-rents/generate', [MonthlyRentController::class, 'generate'])->name('monthly-rents.generate');
            Route::post('monthly-rents/{monthly_rent}/collect', [MonthlyRentController::class, 'collect'])->name('monthly-rents.collect');
            Route::delete('monthly-rents/{monthly_rent}', [MonthlyRentController::class, 'destroy'])->name('monthly-rents.destroy');

            // --- Module 7: Payment Ledger ---
            Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');
            Route::get('ledger/export/summary', [LedgerController::class, 'exportSummary'])->name('ledger.export.summary');
            Route::get('ledger/{student}', [LedgerController::class, 'show'])->name('ledger.show');
            Route::get('ledger/{student}/pdf', [LedgerController::class, 'pdf'])->name('ledger.pdf');
            Route::get('ledger/{student}/excel', [LedgerController::class, 'excel'])->name('ledger.excel');

            // --- Module 8: AC Bills ---
            Route::get('ac-bills', [AcBillController::class, 'index'])->name('ac-bills.index');
            Route::get('ac-bills/create', [AcBillController::class, 'create'])->name('ac-bills.create');
            Route::post('ac-bills', [AcBillController::class, 'store'])->name('ac-bills.store');
            Route::get('ac-bills/{ac_bill}', [AcBillController::class, 'show'])->name('ac-bills.show');
            Route::delete('ac-bills/{ac_bill}', [AcBillController::class, 'destroy'])->name('ac-bills.destroy');
            Route::post('ac-bills/shares/{share}/collect', [AcBillController::class, 'collect'])->name('ac-bills.collect');

            // --- Module 9: Reports ---
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');

            // --- Payment modes (manageable) ---
            Route::resource('payment-modes', PaymentModeController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            // --- Promise to pay (set on an unpaid obligation) ---
            Route::put('promise/{type}/{id}', [PromiseController::class, 'update'])->name('promise.update');

            // --- Add-on: Expense Management ---
            Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

            // --- Add-on: Visitor Register ---
            Route::get('visitors', [VisitorController::class, 'index'])->name('visitors.index');
            Route::post('visitors', [VisitorController::class, 'store'])->name('visitors.store');
            Route::patch('visitors/{visitor}/checkout', [VisitorController::class, 'checkout'])->name('visitors.checkout');
            Route::delete('visitors/{visitor}', [VisitorController::class, 'destroy'])->name('visitors.destroy');

            // --- Add-on: Complaints / Tickets ---
            Route::get('complaints', [ComplaintController::class, 'index'])->name('complaints.index');
            Route::post('complaints', [ComplaintController::class, 'store'])->name('complaints.store');
            Route::patch('complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');
            Route::delete('complaints/{complaint}', [ComplaintController::class, 'destroy'])->name('complaints.destroy');

            // --- New module: Staff (salary + attendance) ---
            Route::get('staff/attendance', [\App\Http\Controllers\Admin\StaffController::class, 'attendance'])->name('staff.attendance');
            Route::post('staff/attendance', [\App\Http\Controllers\Admin\StaffController::class, 'saveAttendance'])->name('staff.attendance.save');
            Route::resource('staff', \App\Http\Controllers\Admin\StaffController::class)->only(['index', 'store', 'update', 'destroy', 'show']);
            Route::post('staff/{staff}/salary', [\App\Http\Controllers\Admin\StaffController::class, 'paySalary'])->name('staff.salary');
            Route::delete('staff/{staff}/salary/{payment}', [\App\Http\Controllers\Admin\StaffController::class, 'deleteSalary'])->name('staff.salary.destroy');

            // --- New module: Pocket money ---
            Route::get('pocket-money', [\App\Http\Controllers\Admin\PocketMoneyController::class, 'index'])->name('pocket-money.index');
            Route::get('pocket-money/{student}', [\App\Http\Controllers\Admin\PocketMoneyController::class, 'show'])->name('pocket-money.show');
            Route::post('pocket-money/{student}', [\App\Http\Controllers\Admin\PocketMoneyController::class, 'store'])->name('pocket-money.store');
            Route::delete('pocket-money/{student}/tx/{transaction}', [\App\Http\Controllers\Admin\PocketMoneyController::class, 'destroy'])->name('pocket-money.destroy');

            // --- New module: Users & roles (sub-users) ---
            Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
            Route::post('users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
            Route::patch('users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('users.reset');
            Route::delete('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');

            // --- New module: Student self-registrations (link/QR + approvals) ---
            Route::get('registrations', [\App\Http\Controllers\Admin\RegistrationController::class, 'index'])->name('registrations.index');
            Route::post('registrations/regenerate', [\App\Http\Controllers\Admin\RegistrationController::class, 'regenerate'])->name('registrations.regenerate');
            Route::post('registrations/{registration}/approve', [\App\Http\Controllers\Admin\RegistrationController::class, 'approve'])->name('registrations.approve');
            Route::post('registrations/{registration}/reject', [\App\Http\Controllers\Admin\RegistrationController::class, 'reject'])->name('registrations.reject');
        });
});
