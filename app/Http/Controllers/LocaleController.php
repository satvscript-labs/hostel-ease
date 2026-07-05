<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function switch(string $locale): RedirectResponse
    {
        if (array_key_exists($locale, config('app.available_locales', []))) {
            session(['locale' => $locale]);
        }

        return back();
    }
}
