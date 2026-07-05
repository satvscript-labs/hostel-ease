<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to one or more roles, e.g. `->middleware('role:super_admin')`.
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Your account is inactive.');
        }

        if (! empty($roles) && ! in_array($user->role, $roles, true)) {
            abort(403, 'You are not authorised to access this area.');
        }

        return $next($request);
    }
}
