<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks any hostel login (owner or staff sub-user) whose active branch's
 * subscription has lapsed or been suspended. Super Admins bypass this check.
 */
class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isHostelStaff()) {
            // Check the subscription of the ACTIVE branch (not just the primary).
            $hostel = \App\Support\Tenant::id()
                ? \App\Models\Hostel::find(\App\Support\Tenant::id())
                : $user->hostel;

            if (! $hostel || ! $hostel->isActive()) {
                // Owners land on their Subscription page (where they can renew);
                // staff sub-users (who can't pay) see the read-only expired screen.
                $target = $user->isHostelAdmin() ? 'admin.subscription.index' : 'subscription.expired';

                return redirect()
                    ->route($target)
                    ->with('warning', 'Your hostel subscription has expired. Please renew to continue.');
            }
        }

        return $next($request);
    }
}
