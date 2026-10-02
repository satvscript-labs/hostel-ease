<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The legacy per-branch billing ARCHIVE (read-only since S1 / decision D8).
 *
 * `subscriptions` was the original per-branch ledger. Nothing writes it any more —
 * every charge is a `subscription_order` with per-branch lines, which is the only
 * ledger (see AccountBillingService). This page survives because the historical rows
 * do, and an operator occasionally needs to look one up.
 *
 * Everything it used to DO now lives where it belongs:
 *   · record a charge, renew, align, comp  → Customers → Account 360
 *   · accept a pending payment             → Account 360 → Orders → Accept
 *   · write off a mistaken record          → Account 360 → Orders → Void
 *   · per-branch billing history           → the hostel profile's Billing card
 *
 * It is not linked from the sidebar; reachable by URL as a power-user fallback.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $subscriptions = Subscription::with('hostel')
            ->when($request->filled('status'), fn ($q) => $q->where('payment_status', $request->string('status')))
            ->orderByDesc('end_date')
            ->paginate(20)
            ->withQueryString();

        // Lifetime totals from the archive only — the live figures come from the
        // order ledger (the Super Admin dashboard and Customers page read that).
        $summary = [
            'records' => Subscription::count(),
            'paid' => (float) Subscription::where('payment_status', 'paid')->sum('amount'),
            'pending' => (float) Subscription::where('payment_status', 'pending')->sum('amount'),
            'last_written' => Subscription::max('created_at'),
        ];

        return view('superadmin.subscriptions.index', compact('subscriptions', 'summary'));
    }
}
