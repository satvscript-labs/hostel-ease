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

        if (! empty($roles)) {
            $allowed = false;
            foreach ($roles as $r) {
                if ($r === 'staff' && method_exists($user, 'isHostelStaff') && $user->isHostelStaff()) {
                    $allowed = true;
                    break;
                }
                if ($user->role === $r) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                return redirect()->back()->with('error', 'You are not authorised to access this area.');
            }
        }

        return $next($request);
    }
}
