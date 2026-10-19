<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The one place a web session is created after a password (and, when it is on, the
 * second step) has been checked — so the plain sign-in and the two-factor sign-in
 * cannot drift apart.
 */
class SignIn
{
    public function __construct(protected ActivityLogger $logger) {}

    public function complete(Request $request, User $user, bool $remember, string $how = 'password'): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        // S7.1: the language follows the person. A choice made on the sign-in page,
        // before they had one saved, becomes theirs.
        $chosen = $request->session()->get('locale');
        if (! $user->isSuperAdmin()) {
            if ($user->locale) {
                $request->session()->put('locale', $user->locale);
            } elseif ($chosen && array_key_exists($chosen, config('app.available_locales', []))) {
                $user->forceFill(['locale' => $chosen])->save();
            }
        }

        $this->logger->log('login', $how === 'password' ? 'User logged in' : 'User logged in ('.$how.')');

        return redirect()->intended(route('dashboard'));
    }
}
