<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates an API route to a feature "area" based on the user's role, and enforces
 * read-only roles (viewer). Usage: ->middleware('access:finance').
 * The hostel owner (hostel_admin) bypasses all area checks.
 */
class CheckAccess
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Owner has full access.
        if ($user->isHostelAdmin()) {
            return $next($request);
        }

        if (! $user->canAccessArea($area)) {
            return redirect()->back()->with('error', 'Your role does not have access to this section.');
        }

        // Read-only roles may only perform safe (GET/HEAD) requests.
        if ($user->isReadonly() && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return redirect()->back()->with('error', 'Your role has read-only access.');
        }

        return $next($request);
    }
}
