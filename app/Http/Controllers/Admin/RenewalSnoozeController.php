<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RenewalReminderSnooze;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\RenewalReminders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Remind me later" on the dashboard renewal card (S4 · 24_S4_DESIGN.md §7).
 *
 * Only quiets the card, for this viewer, for this cycle. Emails and the bell keep
 * their own schedule. The choices are computed from the renewal date on the server —
 * the browser sends "N days before", never a date — and none are open in the last
 * days, so the card cannot be silenced through the renewal date (E18).
 */
class RenewalSnoozeController extends Controller
{
    public function __construct(
        protected AccountBillingService $billing,
        protected RenewalReminders $reminders,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['before' => ['required', 'integer']]);
        $account = $this->account($request);

        $choices = $account->current_period_end ? $this->reminders->snoozeChoices($account) : [];
        $until = $choices[(int) $data['before']] ?? null;

        if (! $until) {
            return back()->with('warning', __('Reminders can no longer be paused — your renewal is too close.'));
        }

        RenewalReminderSnooze::updateOrCreate(
            ['user_id' => $request->user()->id, 'account_id' => $account->id, 'cycle_end' => $account->current_period_end->toDateString()],
            ['until' => $until->toDateString()],
        );

        return back()->with('success', __('Got it — we will remind you on :date.', ['date' => $until->format('d M')]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $account = $this->account($request);

        RenewalReminderSnooze::where('user_id', $request->user()->id)->where('account_id', $account->id)->delete();

        return back();
    }

    /** The account the dashboard card was showing: the active branch's, as DashboardController reads it. */
    protected function account(Request $request): \App\Models\SubscriptionAccount
    {
        $branch = \App\Support\Tenant::id() ? \App\Models\Hostel::find(\App\Support\Tenant::id()) : null;

        return ($branch ? $this->billing->accountForBranch($branch) : null)
            ?? $this->billing->accountForViewer($request->user());
    }
}
