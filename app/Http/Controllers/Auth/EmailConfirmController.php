<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * The link in a team invite. Signed (middleware), so it cannot be forged or edited,
 * and bound to the address it was sent to: if the owner has since changed that address
 * the hash no longer matches and nothing is verified.
 */
class EmailConfirmController extends Controller
{
    public function __invoke(string $user, string $hash): RedirectResponse
    {
        $member = User::where('public_id', $user)->first();

        $valid = $member && $member->email && hash_equals(sha1(mb_strtolower($member->email)), $hash);

        if (! $valid) {
            return redirect()->route('login')->with('warning', __('That link is no longer valid. Ask your manager to send a new one.'));
        }

        if (! $member->email_verified_at) {
            $member->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect()->route('login')->with('success', __('Email confirmed. You can log in with your mobile number.'));
    }
}
