<?php

use App\Http\Controllers\Admin\AcBillController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\BedController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\FloorController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentModeController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentDocumentController;
use App\Http\Controllers\Admin\VisitorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SuperAdmin\AccountController;
use App\Http\Controllers\SuperAdmin\AdminController;
use App\Http\Controllers\SuperAdmin\DiscountController;
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
Route::get('/', fn () => Auth::check() ? redirect()->route('dashboard') : view('welcome'));

// Language switcher (available to everyone, incl. the login page).
Route::get('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// One-time web installer (token-guarded; 404 when SETUP_TOKEN is blank).
Route::get('__install/{token}', [SetupController::class, 'install'])->name('setup.install');

// Public student self-registration form (per-hostel token/QR link).
Route::get('register/{token}', [\App\Http\Controllers\PublicRegistrationController::class, 'show'])->name('public.register');
Route::post('register/{token}', [\App\Http\Controllers\PublicRegistrationController::class, 'submit'])->middleware('throttle:15,1');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:6,1')->name('login.attempt');
    
    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'register'])->middleware('throttle:6,1')->name('register.attempt');
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

    // Branch Management & Subscriptions (hostel owner). Deliberately OUTSIDE the
    // subscription.active gate so an expired owner can still reach the pay page and manage branches.
    Route::middleware('role:hostel_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::post('branches', [\App\Http\Controllers\Admin\BranchManagerController::class, 'store'])->name('branches.store');
        Route::post('branches/order', [\App\Http\Controllers\Admin\BranchManagerController::class, 'createOrder'])->name('branches.order');
        Route::post('branches/verify', [\App\Http\Controllers\Admin\BranchManagerController::class, 'verify'])->name('branches.verify');

        // Owner self-serve consolidated billing (Phase 6)
        Route::get('subscription', [\App\Http\Controllers\Admin\SubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('subscription/renew-order', [\App\Http\Controllers\Admin\SubscriptionController::class, 'renewOrder'])->name('subscription.renew-order');
        Route::post('subscription/add-branch-order', [\App\Http\Controllers\Admin\SubscriptionController::class, 'addBranchOrder'])->name('subscription.add-branch-order');
        Route::post('subscription/verify', [\App\Http\Controllers\Admin\SubscriptionController::class, 'verify'])->name('subscription.verify');
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

        // Customers / Accounts (account-level billing control terminal)
        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
        Route::post('accounts/{account}/renew', [AccountController::class, 'renew'])->name('accounts.renew');
        Route::post('accounts/{account}/add-branch', [AccountController::class, 'addBranch'])->name('accounts.add-branch');
        Route::post('accounts/{account}/add-hostel', [AccountController::class, 'addHostel'])->name('accounts.add-hostel');
        Route::post('accounts/{account}/align', [AccountController::class, 'align'])->name('accounts.align');
        Route::post('accounts/{account}/comp', [AccountController::class, 'comp'])->name('accounts.comp');
        Route::post('accounts/{account}/override', [AccountController::class, 'override'])->name('accounts.override');
        Route::post('accounts/{account}/suspend', [AccountController::class, 'suspend'])->name('accounts.suspend');
        Route::post('accounts/{account}/reactivate', [AccountController::class, 'reactivate'])->name('accounts.reactivate');
        Route::post('accounts/{account}/discounts', [AccountController::class, 'storeDiscount'])->name('accounts.discounts.store');
        Route::delete('accounts/{account}/discounts/{discount}', [AccountController::class, 'revokeDiscount'])->name('accounts.discounts.revoke');

        // Discounts management (volume tiers + manual discount overview)
        Route::get('discounts', [DiscountController::class, 'index'])->name('discounts.index');
        Route::post('discounts/rules', [DiscountController::class, 'storeRule'])->name('discounts.rules.store');
        Route::put('discounts/rules/{rule}', [DiscountController::class, 'updateRule'])->name('discounts.rules.update');
        Route::patch('discounts/rules/{rule}/toggle', [DiscountController::class, 'toggleRule'])->name('discounts.rules.toggle');
        Route::delete('discounts/rules/{rule}', [DiscountController::class, 'destroyRule'])->name('discounts.rules.destroy');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::put('subscriptions/{subscription}', [SubscriptionController::class, 'update'])->name('subscriptions.update');
        Route::patch('subscriptions/{subscription}/accept', [SubscriptionController::class, 'accept'])->name('subscriptions.accept');
        Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');

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
    Route::middleware(['role:staff', 'subscription.active'])
        ->prefix('admin')->name('admin.')->group(function () {
            Route::get('dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

            // --- Module 1: Property Board ---
            Route::middleware('access:property')->group(function () {
                Route::get('property', [\App\Http\Controllers\Admin\PropertyController::class, 'index'])->name('property.index');
                Route::post('floors/reorder', [FloorController::class, 'reorder'])->name('floors.reorder');
                Route::patch('floors/sharing-settings', [FloorController::class, 'updateSharingSettings'])->name('floors.sharing-settings');
                Route::resource('floors', FloorController::class)->only(['index', 'store', 'update', 'destroy']);
                Route::resource('rooms', RoomController::class)->only(['store', 'update', 'destroy']);
                Route::get('beds/{bed}/history', [BedController::class, 'history'])->name('beds.history');
                Route::patch('beds/{bed}/status', [BedController::class, 'updateStatus'])->name('beds.status');
                Route::post('property/assign', [PropertyController::class, 'assign'])->name('property.assign');
                Route::patch('property/assignments/{assignment}/release', [PropertyController::class, 'release'])->name('property.release');
                Route::patch('property/assignments/{assignment}/transfer', [PropertyController::class, 'transfer'])->name('property.transfer');
            });

            // --- Module 2: Students ---
            Route::middleware('access:students')->group(function () {
                Route::resource('students', StudentController::class);
                Route::put('students/{student}/fee-settings', [StudentController::class, 'updateFeeSettings'])->name('students.update-fee-settings');
                Route::post('students/{student}/collect', [StudentController::class, 'collect'])->name('students.collect');
                Route::post('students/{student}/promise', [StudentController::class, 'promise'])->name('students.promise');
                Route::put('students/{student}/fee-settings', [StudentController::class, 'updateFeeSettings'])->name('students.fee-settings.update');
                Route::get('students/{student}/prorate-preview', [StudentController::class, 'previewProration'])->name('students.prorate-preview');
                Route::post('students/{student}/documents', [StudentDocumentController::class, 'store'])->name('students.documents.store');
                Route::delete('students/{student}/documents/{document}', [StudentDocumentController::class, 'destroy'])->name('students.documents.destroy');
                
                // Student self-registrations
                Route::get('registrations', [\App\Http\Controllers\Admin\RegistrationController::class, 'index'])->name('registrations.index');
                Route::post('registrations/regenerate', [\App\Http\Controllers\Admin\RegistrationController::class, 'regenerate'])->name('registrations.regenerate');
                Route::post('registrations/{registration}/approve', [\App\Http\Controllers\Admin\RegistrationController::class, 'approve'])->name('registrations.approve');
                Route::post('registrations/{registration}/reject', [\App\Http\Controllers\Admin\RegistrationController::class, 'reject'])->name('registrations.reject');
            });

            // --- Module 5: Finances (Invoices & Payments) ---
            Route::middleware('access:finance')->group(function () {
                Route::get('finances', [FinanceController::class, 'index'])->name('finance.index');
                Route::post('finances/generate-fee', [FinanceController::class, 'generateFee'])->name('finance.generate-fee');
                
                // Invoices
                Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
                Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

                // Payments (View, delete, receipt actions)
                Route::get('payments/{payment}/pdf', [PaymentController::class, 'pdf'])->name('payments.pdf');
                Route::post('payments/{payment}/whatsapp', [PaymentController::class, 'whatsapp'])->name('payments.whatsapp');
                Route::post('payments/{payment}/email', [PaymentController::class, 'email'])->name('payments.email');
                Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

                // Payment modes
                Route::resource('payment-modes', PaymentModeController::class)->only(['index', 'store', 'update', 'destroy']);

                // AC Bills
                Route::resource('ac-bills', AcBillController::class)->only(['index', 'store', 'destroy']);

                // Expense Management
                Route::get('expenses', [\App\Http\Controllers\Admin\ExpenseController::class, 'index'])->name('expenses.index');
                Route::post('expenses', [\App\Http\Controllers\Admin\ExpenseController::class, 'store'])->name('expenses.store');
                Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
                
                // Pocket money
                Route::get('pocket-money', [\App\Http\Controllers\Admin\PocketMoneyController::class, 'index'])->name('pocket-money.index');
                Route::get('pocket-money/{student}', [\App\Http\Controllers\Admin\PocketMoneyController::class, 'show'])->name('pocket-money.show');
                Route::post('pocket-money/{student}', [\App\Http\Controllers\Admin\PocketMoneyController::class, 'store'])->name('pocket-money.store');
                Route::delete('pocket-money/{student}/tx/{transaction}', [\App\Http\Controllers\Admin\PocketMoneyController::class, 'destroy'])->name('pocket-money.destroy');
                
                // Security Deposits
                Route::get('security-deposits', [\App\Http\Controllers\Admin\SecurityDepositController::class, 'index'])->name('security-deposits.index');
                Route::post('security-deposits', [\App\Http\Controllers\Admin\SecurityDepositController::class, 'store'])->name('security-deposits.store');
                Route::post('security-deposits/{securityDeposit}/refund', [\App\Http\Controllers\Admin\SecurityDepositController::class, 'refund'])->name('security-deposits.refund');
                Route::post('security-deposits/{securityDeposit}/revert-refund', [\App\Http\Controllers\Admin\SecurityDepositController::class, 'revertRefund'])->name('security-deposits.revert-refund');
            });

            // --- Module 9: Reports ---
            Route::middleware('access:reports')->group(function () {
                Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
                Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');
            });

            // --- Add-on: Front Desk (Visitors & Complaints) ---
            Route::middleware('access:people')->group(function () {
                Route::get('frontdesk', [\App\Http\Controllers\Admin\FrontDeskController::class, 'index'])->name('frontdesk.index');
                Route::post('visitors', [VisitorController::class, 'store'])->name('visitors.store');
                Route::patch('visitors/{visitor}/checkout', [VisitorController::class, 'checkout'])->name('visitors.checkout');
                Route::delete('visitors/{visitor}', [VisitorController::class, 'destroy'])->name('visitors.destroy');
                Route::post('complaints', [ComplaintController::class, 'store'])->name('complaints.store');
                Route::patch('complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');
                Route::delete('complaints/{complaint}', [ComplaintController::class, 'destroy'])->name('complaints.destroy');
            });

            // --- New module: Staff (salary + attendance) ---
            Route::middleware('access:staff')->group(function () {
                Route::post('staff/attendance', [\App\Http\Controllers\Admin\StaffController::class, 'saveAttendance'])->name('staff.attendance.save');
                Route::resource('staff', \App\Http\Controllers\Admin\StaffController::class)->only(['index', 'store', 'update', 'destroy', 'show']);
                Route::post('staff/{staff}/salary', [\App\Http\Controllers\Admin\StaffController::class, 'paySalary'])->name('staff.salary');
                Route::delete('staff/{staff}/salary/{payment}', [\App\Http\Controllers\Admin\StaffController::class, 'deleteSalary'])->name('staff.salary.destroy');
            });

            // --- New module: Users & roles (sub-users) ---
            Route::middleware('access:users')->group(function () {
                Route::post('users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
                Route::put('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
                Route::patch('users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('users.reset');
                Route::delete('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');
            });
        });
});
