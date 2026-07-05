<?php

namespace App\Providers;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Avoid index length errors on older MySQL/MariaDB.
        Schema::defaultStringLength(191);

        // Guard against lazy loading + silent attribute mismatches in non-prod.
        Model::shouldBeStrict(! $this->app->isProduction());

        Paginator::useBootstrapFive();

        // Feed the topbar notification bell on every authenticated page.
        View::composer('partials.topbar', function ($view) {
            $user = Auth::user();
            $recent = $user
                ? Notification::forUser($user)->unread()->latest()->limit(8)->get()
                : collect();

            $view->with('navNotifications', $recent)
                ->with('navNotificationCount', $recent->count());
        });
    }
}
