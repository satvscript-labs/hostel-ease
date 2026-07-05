<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the user's chosen locale (stored in session) for the request,
 * falling back to the app default. Only locales listed in config('app.available_locales')
 * are honoured.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            $locale = $request->session()->get('locale', config('app.locale'));

            if (array_key_exists($locale, config('app.available_locales', []))) {
                App::setLocale($locale);
            }
        }

        return $next($request);
    }
}
