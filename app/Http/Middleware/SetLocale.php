<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language for this request (S7.1 · 34_S7_1_DESIGN.md §2):
 *   - the Super Admin always reads English (D-S7-2);
 *   - a signed-in person reads the language saved on their account;
 *   - a guest (sign-in, sign-up, student registration) the one picked in this session;
 *   - otherwise the app default.
 * Only locales in config('app.available_locales') are honoured.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = config('app.available_locales', []);
        $user = $request->user();

        $locale = match (true) {
            $user?->isSuperAdmin() => 'en',
            $user && array_key_exists((string) $user->locale, $available) => $user->locale,
            $request->hasSession() => $request->session()->get('locale', config('app.locale')),
            default => config('app.locale'),
        };

        if (array_key_exists($locale, $available)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
