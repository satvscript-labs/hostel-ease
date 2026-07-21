<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Presence module access — an EXPLICIT allow-list, deliberately NOT the generic
 * `access:` area gate (owner Q6, 01 §8).
 *
 * Why not `access:presence`: `viewer` carries role_access ['*'], so the generic
 * wildcard check would admit it read-only — but the owner wants Presence
 * restricted to owner + co-admins + manager + warden, with everyone else
 * (accountant, viewer) excluded entirely. So we gate on the concrete roles.
 */
class CheckPresenceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Same allow-list the sidebar and User::canAccessPresence() use.
        if (! $user->canAccessPresence()) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Your role does not have access to Presence.');
        }

        return $next($request);
    }
}
