<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the active hostel (tenant) for the request based on the
 * authenticated user. Super Admins are left unbound so they can
 * operate across every hostel.
 */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isHostelAdmin()) {
            $accessible = $user->accessibleHostelIds();
            $active = $request->session()->get('active_hostel_id');

            // Fall back to primary/first branch if none chosen or no longer allowed.
            if (! $active || ! in_array($active, $accessible, true)) {
                $active = $user->hostel_id ?: ($accessible[0] ?? null);
                if ($active) {
                    $request->session()->put('active_hostel_id', $active);
                }
            }

            Tenant::set($active);
        } else {
            Tenant::clear();
        }

        return $next($request);
    }
}
