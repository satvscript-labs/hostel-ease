<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stateless tenant resolver for the mobile/API guard.
 *
 * Unlike the web SetTenant middleware (which reads the session), the active
 * branch here is taken from the `X-Hostel-Id` request header so each token
 * request is self-describing. It is validated against the user's accessible
 * branches and falls back to their primary hostel.
 */
class ApiTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isHostelStaff()) {
            // Mirror the token user onto the default guard so Auth::id()/user()
            // (used by ActivityLogger, PaymentService, etc.) resolve correctly.
            Auth::setUser($user);

            $accessible = $user->accessibleHostelIds();
            $requested = (int) $request->header('X-Hostel-Id', 0) ?: null;

            $active = ($requested && in_array($requested, $accessible, true))
                ? $requested
                : ($user->hostel_id ?: ($accessible[0] ?? null));

            Tenant::set($active);
        } else {
            Tenant::clear();
        }

        return $next($request);
    }
}
