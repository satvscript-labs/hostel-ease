<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffInviteMail;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }



    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => $this->cleanEmail($request->input('email'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'regex:/^\+91\d{10}$|^\d{10}$/', Rule::unique('users', 'mobile')->whereNull('deleted_at')],
            'email' => ['nullable', 'email:rfc', 'max:150', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(config('hostelease.staff_roles')))],
            'branches' => ['required', 'array', 'min:1'],
            'branches.*' => ['integer', 'exists:hostels,id'],
        ]);

        // Only branches THIS owner can access may be assigned (P4 item 15 —
        // previously any hostel id passed validation).
        $branchIds = $this->allowedBranchIds($request, $data['branches']);
        abort_unless(count($branchIds) > 0, 422);

        $digits = substr(preg_replace('/\D+/', '', $data['mobile']), -10);
        $mobile = '+91' . $digits;

        $password = Str::upper(Str::random(3)).random_int(10000, 99999);
        $user = User::create([
            'hostel_id' => Tenant::id(),
            'name' => $data['name'],
            'mobile' => $mobile,
            'password' => Hash::make($password),
            'role' => $data['role'],
            'email' => $this->cleanEmail($data['email'] ?? null),
            'is_active' => true,
        ]);

        $user->hostels()->sync($branchIds);
        $this->logger->log('user.create', "Added {$data['role']} {$user->name}", $user);

        $response = back()->with('active_tab', 'users')->with('credentials', ['mobile' => $user->mobile, 'password' => $password])
            ->with('success', __('User created — share the login below.'));

        // A team member with an email gets a welcome and a one-tap confirmation link.
        // Mail trouble must never undo the account that was just made.
        if ($user->email && ! $this->sendInvite($user)) {
            $response->with('success', null)->with('warning', __('User created, but we could not email :email. Use "Send verification" on their row to try again.', ['email' => $user->email]));
        }

        return $response;
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($user);
        $request->merge(['email' => $this->cleanEmail($request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(config('hostelease.staff_roles')))],
            'branches' => ['required', 'array', 'min:1'],
            'branches.*' => ['integer', 'exists:hostels,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branchIds = $this->allowedBranchIds($request, $data['branches']);
        abort_unless(count($branchIds) > 0, 422);

        $email = $this->cleanEmail($data['email'] ?? null);
        $emailChanged = $email !== ($user->email ? mb_strtolower($user->email) : null);

        $user->update([
            'name' => $data['name'],
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ]);

        // A different address is a different, unproven address: never keep the old
        // "verified" mark on it.
        if ($emailChanged) {
            $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();
        }

        $user->hostels()->sync($branchIds);

        $response = back()->with('active_tab', 'users')->with('success', __('User updated.'));

        if ($emailChanged && $email && ! $this->sendInvite($user->refresh())) {
            $response->with('success', null)->with('warning', __('Saved, but we could not email :email. Use "Send verification" on their row to try again.', ['email' => $email]));
        }

        return $response;
    }

    /** Email the team member their one-tap confirmation link again. */
    public function resendVerification(User $user): RedirectResponse
    {
        $this->authorizeManage($user);

        if (! $user->email) {
            return back()->with('active_tab', 'users')->with('warning', __('Add an email to :name first.', ['name' => $user->name]));
        }

        if ($user->email_verified_at) {
            return back()->with('active_tab', 'users')->with('success', __(':name\'s email is already verified.', ['name' => $user->name]));
        }

        return $this->sendInvite($user)
            ? back()->with('active_tab', 'users')->with('success', __('Verification sent to :email.', ['email' => $user->email]))
            : back()->with('active_tab', 'users')->with('warning', __('We could not email :email. Check the address and try again.', ['email' => $user->email]));
    }

    protected function cleanEmail(?string $email): ?string
    {
        $email = $email === null ? '' : mb_strtolower(trim($email));

        return $email === '' ? null : $email;
    }

    protected function sendInvite(User $user): bool
    {
        try {
            $user->loadMissing('hostels:id,name');
            $actor = auth()->user();

            Mail::to($user->email)->send(new StaffInviteMail(
                $user,
                config('hostelease.staff_roles.'.$user->role, ucfirst($user->role)),
                $user->hostels->pluck('name')->implode(', ') ?: config('app.name', 'HostelEase'),
                $actor?->name ?? config('app.name', 'HostelEase'),
            ));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Team invite email could not be sent', ['user' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Enable/disable a login. Co-admins allowed (operational control); the
     *  account owner never (P4 item 14/16). */
    public function toggle(User $user): RedirectResponse
    {
        $this->authorizeManage($user, adminAllowed: true);
        $user->update(['is_active' => ! $user->is_active]);
        $this->logger->log('user.toggle', ($user->is_active ? 'Enabled' : 'Disabled')." {$user->name}", $user);

        return back()->with('active_tab', 'users')->with('success', 'User '.($user->is_active ? 'enabled' : 'disabled').'.');
    }

    public function resetPassword(User $user): RedirectResponse
    {
        // Co-admins get operational control (reset), but never the owner.
        $this->authorizeManage($user, adminAllowed: true);
        $password = Str::upper(Str::random(3)).random_int(10000, 99999);
        $user->update(['password' => Hash::make($password)]);

        return back()->with('active_tab', 'users')->with('credentials', ['mobile' => $user->mobile, 'password' => $password])
            ->with('success', 'Password reset — share the new login below.');
    }

    public function destroy(User $user): RedirectResponse
    {
        // Deleting is staff-only — co-admins are removed via the Super Admin.
        $this->authorizeManage($user);
        $user->delete();

        return back()->with('active_tab', 'users')->with('success', 'User removed.');
    }

    /**
     * Manageability from the owner panel (P4 item 16):
     *  - the account OWNER is never manageable here (super-admin territory),
     *  - the target must share a branch with the acting admin (item-14 access),
     *  - staff are always manageable; a co-admin (hostel_admin, non-owner) only
     *    when $adminAllowed — used for the read-only-ish disable/reset actions,
     *    never edit/delete/role-change.
     */
    protected function authorizeManage(User $user, bool $adminAllowed = false): void
    {
        abort_if($user->isOwner(), 403);

        $shared = count(array_intersect(auth()->user()->accessibleHostelIds(), $user->accessibleHostelIds())) > 0;
        abort_unless($shared, 403);

        $isStaff = array_key_exists($user->role, config('hostelease.staff_roles'));
        abort_unless($isStaff || ($adminAllowed && $user->isHostelAdmin()), 403);
    }

    /** Intersect requested branch ids with what the acting owner can access. */
    protected function allowedBranchIds(Request $request, array $requested): array
    {
        return array_values(array_intersect(
            array_map('intval', $requested),
            $request->user()->accessibleHostelIds(),
        ));
    }
}

