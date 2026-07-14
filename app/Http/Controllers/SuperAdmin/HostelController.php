<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHostelRequest;
use App\Models\Hostel;
use App\Services\ActivityLogger;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HostelController extends Controller
{
    public function __construct(
        protected HostelService $hostels,
        protected ActivityLogger $logger,
        protected AccountBillingService $billing,
    ) {
    }

    public function index(\Illuminate\Http\Request $request): View
    {
        $hostels = Hostel::withCount('students')
            ->withCount(['users as admins_count' => fn ($q) => $q->where('role', 'hostel_admin')])
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->string('q'));
                $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                    ->orWhere('owner_name', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // Fleet-health tiles for the redesigned header (P4 item 13).
        $stats = [
            'total' => Hostel::count(),
            'active' => Hostel::where('status', 'active')->count(),
            'expiring' => Hostel::where('status', 'active')
                ->whereNotNull('subscription_end')
                ->whereBetween('subscription_end', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
                ->count(),
            'expired' => Hostel::where('status', 'expired')->count(),
        ];

        $hostelsJson = collect($hostels->items())->mapWithKeys(fn ($h) => [
            $h->id => [
                'name' => $h->name,
                'owner_name' => $h->owner_name,
                'mobile' => $h->mobile,
                'email' => $h->email,
                'address' => $h->address,
                'city' => $h->city,
                'state' => $h->state,
                'gst_number' => $h->gst_number,
                'subscription_start' => optional($h->subscription_start)->format('Y-m-d'),
                'subscription_end' => optional($h->subscription_end)->format('Y-m-d'),
                'status' => $h->status,
            ],
        ]);
        
        $pricingJson = [
            'yearly' => config('hostelease.subscription_pricing.yearly', 10000),
            'monthly' => config('hostelease.subscription_pricing.monthly', 1000),
            'trial' => 0,
        ];

        // Owner-account deep-link per row (P4 item 3.2), resolved in two batched
        // queries rather than per-row.
        $mobiles = collect($hostels->items())->pluck('mobile')->filter()->unique();
        $owners = \App\Models\User::whereIn('mobile', $mobiles)->where('role', 'hostel_admin')->get(['id', 'mobile'])->keyBy('mobile');
        $accounts = \App\Models\SubscriptionAccount::whereIn('owner_id', $owners->pluck('id'))->get(['id', 'owner_id'])->keyBy('owner_id');
        $accountByHostel = collect($hostels->items())->mapWithKeys(fn ($h) => [
            $h->id => optional($owners->get($h->mobile), fn ($o) => $accounts->get($o->id)?->id),
        ])->all();

        return view('superadmin.hostels.index', compact('hostels', 'hostelsJson', 'pricingJson', 'accountByHostel', 'stats'));
    }

    /**
     * Editing happens in the profile page's own modal — this route only exists
     * because Route::resource declares it (the old Edit button 500'd on the
     * missing method). Deep-links land on the profile with the modal open.
     */
    public function edit(Hostel $hostel): RedirectResponse
    {
        return redirect()->route('superadmin.hostels.show', [$hostel, 'edit' => 1]);
    }

    public function store(StoreHostelRequest $request): RedirectResponse
    {
        $result = $this->hostels->provision($request->validated());

        $this->logger->log('hostel.provision', "Provisioned hostel {$result['hostel']->name}", $result['hostel']);

        $redirect = redirect()->route('superadmin.hostels.show', $result['hostel']);

        if ($result['password'] === null) {
            // Linked to an existing owner login (same mobile = another branch).
            return $redirect->with('success',
                "Hostel created and linked to existing owner {$result['admin']->mobile} as a new branch. They use their current password.");
        }

        // Surface the generated login once so the Super Admin can share it.
        return $redirect
            ->with('credentials', [
                'mobile' => $result['admin']->mobile,
                'password' => $result['password'],
            ])
            ->with('success', 'Hostel created and admin login generated.');
    }

    public function show(Hostel $hostel): View
    {
        $hostel->loadCount('students', 'rooms', 'beds')
            ->load(['admins.hostels', 'subscriptions' => fn ($q) => $q->latest('end_date')]);

        // The explicit owner FK is authoritative; billing's resolver self-heals
        // legacy rows (mobile / pivot fallbacks) onto it.
        $ownerAdmin = $hostel->owner ?? $this->billing->ownerForBranch($hostel);
        $branches = $ownerAdmin
            ? \App\Models\Hostel::whereIn('id', $ownerAdmin->accessibleHostelIds())->orderBy('name')->get()
            : collect([$hostel]);

        // Where the "Add / Renew" button should send the Super Admin: the new
        // Account 360 terminal when this branch's owner already has an account,
        // falling back to the legacy per-branch page otherwise (e.g. a branch
        // with no linked hostel_admin login yet).
        $account = $this->billing->accountForBranch($hostel);

        return view('superadmin.hostels.show', compact('hostel', 'branches', 'account'));
    }



    public function update(StoreHostelRequest $request, Hostel $hostel): RedirectResponse
    {
        $data = $request->safe()->only([
            'name', 'owner_name', 'mobile', 'email', 'address', 'city', 'state',
            'gst_number', 'subscription_start', 'subscription_end', 'status',
        ]);

        // The hostel mobile doubles as the owner's LOGIN username and as the
        // identity that links sibling branches. Changing it here must move the
        // owner login and every sibling branch with it — otherwise the branch
        // orphans from its owner/account (P4 item 14, decision 3).
        $newMobile = $data['mobile'] ?? null;
        $owner = $hostel->owner;

        if ($newMobile && $newMobile !== $hostel->mobile && $owner) {
            $collision = \App\Models\User::where('mobile', $newMobile)->where('id', '!=', $owner->id)->exists();
            if ($collision) {
                return back()->withInput()->withErrors([
                    'mobile' => 'That mobile already belongs to another login — it cannot become this owner\'s number.',
                ]);
            }

            $owner->forceFill(['mobile' => $newMobile])->save();
            $owner->ownedHostels()->where('id', '!=', $hostel->id)->update(['mobile' => $newMobile]);
            $this->logger->log('hostel.update', "Owner mobile changed to {$newMobile} (login + all owned branches)", $hostel);
        }

        $hostel->update($data);

        $this->logger->log('hostel.update', "Updated hostel {$hostel->name}", $hostel);

        return redirect()->route('superadmin.hostels.show', $hostel)->with('success', 'Hostel updated.');
    }

    public function destroy(Hostel $hostel): RedirectResponse
    {
        $this->logger->log('hostel.delete', "Deleted hostel {$hostel->name}", $hostel);
        $hostel->delete();

        return redirect()->route('superadmin.hostels.index')->with('success', 'Hostel deleted.');
    }
}
