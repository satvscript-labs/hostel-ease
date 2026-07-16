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

        // The design system owns pagination, same as it owns .panel-card: one
        // skin, every ->links() in the app. Stock Bootstrap-5 pagination was
        // off-system (square boxes, a cramped "Showing 1 to 15 of 17 results").
        // See resources/views/vendor/pagination/premium.blade.php + .he-pager.
        Paginator::defaultView('vendor.pagination.premium');
        // NOT the premium view: it needs $elements/total(), which simplePaginate
        // doesn't provide. Nothing uses simplePaginate today; this keeps the
        // Bootstrap-5 fallback so adding one later degrades instead of fataling.
        Paginator::defaultSimpleView('pagination::simple-bootstrap-5');

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
