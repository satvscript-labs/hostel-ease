<?php

use App\Http\Middleware\CheckAccess;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\LogActivity;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum stateful API guard for SPA / PWA requests.
        $middleware->statefulApi();

        // Named middleware aliases used across hostel ease routes.
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'tenant' => SetTenant::class,
            'access' => CheckAccess::class,
            'subscription.active' => EnsureActiveSubscription::class,
            'no-cache' => \App\Http\Middleware\PreventBackHistory::class,
        ]);

        // Append locale + activity logging AFTER the session is started.
        $middleware->web(append: [
            SetLocale::class,
            LogActivity::class,
        ]);

        // CRITICAL (found W6.4): SubstituteBindings ships inside the web
        // group, which runs BEFORE route middleware like 'tenant' — so every
        // route-model binding resolved while Tenant was still unbound, and
        // TenantScope no-opped. Result: ANY authenticated admin could reach
        // ANY hostel's students/invoices/bills/deposits just by putting the
        // id in the URL. Forcing SetTenant ahead of SubstituteBindings in the
        // priority order means the tenant is bound before any model is, and
        // cross-tenant ids 404 the way the scope always promised.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            SetTenant::class,
        );

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            return redirect()->route('login')->with('error', 'Your session has expired. Please log in again.');
        });
    })->create();
