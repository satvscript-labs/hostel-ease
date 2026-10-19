<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Pick a language. Kept in the session (guests), and saved on the account of a
     * signed-in person so it follows them to every device and to their emails (S7.1).
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (array_key_exists($locale, config('app.available_locales', []))) {
            session(['locale' => $locale]);

            $user = $request->user();
            if ($user && ! $user->isSuperAdmin() && $user->locale !== $locale) {
                $user->forceFill(['locale' => $locale])->save();
            }
        }

        return back();
    }
}
