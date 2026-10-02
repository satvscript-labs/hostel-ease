<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function show()
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hostel_name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'size:10'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $mobile = '+91' . preg_replace('/\D+/', '', $request->mobile);
        $mobile = substr($mobile, 0, 3) . substr($mobile, -10);

        if (User::where('mobile', $mobile)->exists()) {
            throw ValidationException::withMessages([
                'mobile' => 'This mobile number is already registered.',
            ]);
        }

        try {
            DB::beginTransaction();

            // 1. Create the Hostel Tenant.
            //
            // NO COVERAGE IS WRITTEN HERE (S1 · the S0 invariant, finally applied to
            // this path too). This used to stamp subscription_start/end inline, which
            // made signup the last place outside the billing service that granted
            // coverage — invisible to the order ledger, so the trial could not be
            // counted, reported on, or converted into a trial→paid figure, and the
            // coverage audit flagged it forever as "entitled with no paid record".
            // The ₹0 trial order is created in step 5 and IS what grants the window.
            $hostel = Hostel::create([
                'name' => $request->hostel_name,
                'owner_name' => $request->name,
                'mobile' => $mobile,
                'status' => 'active',
            ]);

            // 2. Create the Owner User
            $user = User::create([
                'name' => $request->name,
                'mobile' => $mobile,
                'password' => Hash::make($request->password),
                'role' => 'hostel_admin',
                'hostel_id' => $hostel->id,
                'is_active' => true,
            ]);

            // 3. Attach user to hostel in the pivot table (if applicable for multi-branch)
            $user->hostels()->attach($hostel->id);

            // 4. Provision the tenant FULLY (H3) — the super-admin path does this
            // via HostelService, but self-signup skipped it, leaving a new owner
            // with no payment modes (so collect() had no valid mode → couldn't
            // record any payment) and no owner FK / account spine. Fix all three:
            $hostel->update(['owner_id' => $user->id]);
            app(\App\Services\HostelService::class)->seedPaymentModes($hostel);

            // 5. Start the free trial THROUGH THE BILLER (S1). This creates the
            // account spine, a ₹0 `trial` order with one line, and — via the coverage
            // projection — the 14-day window itself. One code path for every grant of
            // coverage in the product, and the trial becomes a measurable event.
            app(\App\Services\Billing\AccountBillingService::class)
                ->recordBranchRenewal($hostel->fresh(), 'trial', [
                    'payment_status' => 'paid',
                    'payment_method' => null,
                    'remarks' => 'Self-signup free trial',
                ]);

            DB::commit();

            // Log them in immediately
            Auth::login($user);

            // Redirect to dashboard
            return redirect()->route('dashboard')->with('success', 'Welcome to HostelEase! Your 14-day free trial has started.');

        } catch (\Exception $e) {
            DB::rollBack();
            throw ValidationException::withMessages([
                'mobile' => 'An error occurred while creating your account. Please try again.',
            ]);
        }
    }
}
