<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHostelRequest;
use App\Models\Hostel;
use App\Services\ActivityLogger;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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

        // Keyed by the OPAQUE public_id, and carrying it as `id`, so the edit
        // modal's by-key fallback lookup builds a URL that actually resolves
        // (public-id hardening U4). The primary path hands the modal the whole
        // payload object from _list, which is already opaque.
        $hostelsJson = collect($hostels->items())->mapWithKeys(fn ($h) => [
            $h->public_id => [
                'id' => $h->public_id,
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
        
        // A NEW customer's first term, priced by the same engine every charge uses —
        // the list unit and any automatic tier for one branch. It replaced a browser-
        // side "Auto-calc" that copied the list price into an editable box.
        $newCustomerQuotes = $this->newCustomerQuotes();
        $linksEnabled = app(\App\Services\Billing\PaymentLinkService::class)->isEnabled();

        // Owner-account deep-link per row (P4 item 3.2), resolved in two batched
        // queries rather than per-row.
        $mobiles = collect($hostels->items())->pluck('mobile')->filter()->unique();
        $owners = \App\Models\User::whereIn('mobile', $mobiles)->where('role', 'hostel_admin')->get(['id', 'mobile'])->keyBy('mobile');
        // public_id is selected because it is the ROUTE KEY the link below is
        // generated from — omitting it makes route() throw under strict mode.
        $accounts = \App\Models\SubscriptionAccount::whereIn('owner_id', $owners->pluck('id'))->get(['id', 'owner_id', 'public_id'])->keyBy('owner_id');
        // Map to the ACCOUNT MODEL, not its id: the list links with
        // route('superadmin.accounts.show', $account) and the route key is the
        // opaque public_id now (public-id hardening U4) — an integer here builds
        // a URL that no longer resolves.
        $accountByHostel = collect($hostels->items())->mapWithKeys(fn ($h) => [
            $h->id => optional($owners->get($h->mobile), fn ($o) => $accounts->get($o->id)),
        ])->all();

        return view('superadmin.hostels.index', compact('hostels', 'hostelsJson', 'newCustomerQuotes', 'linksEnabled', 'accountByHostel', 'stats'));
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

    /**
     * Provision a NEW customer: their first hostel, their login, and their first charge.
     *
     * New customers only. A hostel for someone who is already a customer is added from
     * their Account 360 (Add hostel), where it joins their plan — prorated onto their
     * renewal date, at their price, with their discounts. This form used to accept an
     * existing owner's number and give the branch its own full term at the list price,
     * which could push their whole account's renewal date out by a year.
     *
     * The charge is the shared one: recorded as received, or a payment link (the
     * hostel is created either way and goes live when the money lands). A posted
     * amount may only lower it. Everything — hostel, login, charge, link — is one
     * transaction, so a refused charge or a Razorpay failure leaves nothing behind.
     */
    public function store(StoreHostelRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($existing = $this->existingOwner($data['mobile'])) {
            $account = \App\Models\SubscriptionAccount::where('owner_id', $existing->id)->first();

            return back()->withInput()->withErrors(['mobile' => "This number already belongs to {$existing->name}"
                .($account ? ', an existing customer. Add the hostel from their account so it joins their plan.' : '.')]);
        }

        $viaLink = ($data['collect'] ?? 'offline') === 'link' && ($data['plan'] ?? 'yearly') !== 'trial';
        $data['payment_status'] = $viaLink ? 'pending' : 'paid';
        $data['collection'] = $viaLink ? \App\Enums\CollectionMethod::Link->value : null;
        if ($viaLink) {
            // No instrument yet — the webhook records how it was actually paid.
            unset($data['payment_method'], $data['transaction_number']);
        }

        try {
            $result = null;
            if ($viaLink) {
                app(\App\Services\Billing\PaymentLinkService::class)->collect(function () use (&$result, $data) {
                    $result = $this->hostels->provision($data);

                    return $result['order'];
                });
            } else {
                $result = $this->hostels->provision($data);
            }
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['plan' => \App\Support\Refusal::message($e)]);
        }

        $this->logger->log('hostel.provision', "Provisioned hostel {$result['hostel']->name} for new customer {$result['admin']->name}", $result['hostel']);

        $account = \App\Models\SubscriptionAccount::where('owner_id', $result['admin']->id)->firstOrFail();
        $order = $result['order']?->fresh();

        $redirect = redirect()->route('superadmin.accounts.show', $account)
            ->with('credentials', ['mobile' => $result['admin']->mobile, 'password' => $result['password']]);

        if ($viaLink) {
            // Account 360 opens its Share panel by itself when this is present.
            return $redirect
                ->with('success', "{$result['hostel']->name} is set up. It goes live once the ".hostelease_money($order->amount).' payment link is paid.')
                ->with('payment_link', [
                    'url' => $order->payment_link_url, 'amount' => (float) $order->amount, 'invoice' => $order->invoiceNumber(),
                    'order_id' => $order->id, 'expires' => $order->payment_link_expires_at?->format('d M Y'),
                ]);
        }

        return $redirect->with('success', ($data['plan'] ?? 'yearly') === 'trial'
            ? "{$result['hostel']->name} is set up on a 14-day free trial."
            : "{$result['hostel']->name} is set up and paid — live until ".$account->fresh()->current_period_end?->format('d M Y').'.');
    }

    /**
     * Does this mobile already belong to a customer? Lets the Provision form send the
     * operator to that customer's Account 360 before they fill anything else in.
     */
    public function ownerLookup(Request $request): JsonResponse
    {
        $digits = substr(preg_replace('/\D+/', '', (string) $request->query('mobile')), -10);
        if (strlen($digits) !== 10) {
            return response()->json(['exists' => false]);
        }

        $owner = $this->existingOwner('+91'.$digits);
        if (! $owner) {
            return response()->json(['exists' => false]);
        }

        $account = \App\Models\SubscriptionAccount::where('owner_id', $owner->id)->first();

        return response()->json([
            'exists' => true,
            'name' => $owner->name,
            'branches' => count($owner->accessibleHostelIds()),
            'account_url' => $account ? route('superadmin.accounts.show', [$account, 'add_hostel' => 1]) : null,
        ]);
    }

    /** A hostel-admin login holding this mobile — an existing customer. */
    protected function existingOwner(string $mobile): ?\App\Models\User
    {
        return \App\Models\User::where('mobile', $mobile)->where('role', 'hostel_admin')->first();
    }

    /** @return array<string, array{unit: float, volume: float, auto: float}> */
    protected function newCustomerQuotes(): array
    {
        $discounts = app(\App\Services\Billing\DiscountService::class);
        $blank = new \App\Models\SubscriptionAccount;   // no negotiated price or discounts yet

        return collect(['yearly', 'monthly'])->mapWithKeys(function (string $period) use ($discounts, $blank) {
            $unit = (float) config("hostelease.subscription_pricing.{$period}");
            $p = $discounts->preview($blank, $unit, 1, 'renewal');

            return [$period => ['unit' => $unit, 'volume' => (float) $p['volume_amount'], 'auto' => (float) $p['final']]];
        })->all();
    }

    public function show(Hostel $hostel): View
    {
        $hostel->loadCount('students', 'rooms', 'beds')
            ->load('admins.hostels');

        // Per-branch billing history now comes from the ORDER LEDGER, not the retired
        // legacy `subscriptions` table (S1 · finding F4 / decision D8). That table was
        // never written by consolidated renewals, so this card used to show nothing
        // for a branch renewed through Account 360 — the common case.
        $coverageLines = \App\Models\SubscriptionOrderLine::with('order')
            ->where('branch_id', $hostel->id)
            ->orderByDesc('end_date')
            ->limit(25)
            ->get();

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

        return view('superadmin.hostels.show', compact('hostel', 'branches', 'account', 'coverageLines'));
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
