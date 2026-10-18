<?php

namespace Database\Seeders;

use App\Enums\BillingMode;
use App\Enums\DiscountRecurrence;
use App\Enums\DiscountStatus;
use App\Enums\DiscountType;
use App\Models\Discount;
use App\Models\Hostel;
use App\Models\Notification;
use App\Models\RenewalReminderSnooze;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionMandate;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionReminderLog;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * THE BILLING LAB — one customer per manual test, for S1–S6
 * (_artifact/saas_billing_autopay/27_BILLING_LAB.md lists them all, with what to do).
 *
 *     php artisan db:seed --class=BillingLabSeeder        # build (or rebuild from scratch)
 *     php artisan db:seed --class=BillingLabPurgeSeeder   # remove every lab row
 *
 * Every name says what it is for. A customer is "Lab S1-35 Last Branch": phase S1, test
 * 3.5 of 09_S1_MANUAL_TESTING.md. Its branches are "S1-35 · Only Branch". Logins:
 *
 *     mobile 70 P NN 0000 R    P = phase (1–4) · NN = test number · R = 0 owner, 1 co-admin, 2 staff
 *     e.g. 7013500000 = owner of S1-35 · 7041600001 = co-admin of S4-16       password: password
 *
 * Emails are <code>@lab.hostelease.test — not deliverable. To see a real email, put your own
 * address on one owner (Settings → Profile → Change).
 *
 * Every state is made by the REAL billing services, run at the right moment in the past
 * (the clock is moved with Carbon::setTestNow), so the ledger, coverage and the
 * coverage audit all agree — nothing is written straight into a date column.
 *
 * Re-runnable: it first removes everything it made before (owners on the 70… range named
 * "Lab …", their branches, orders, discounts, reminders, snoozes and logins), then builds
 * it all again from today's date. It never touches anything else.
 *
 * No Faker and no factories: it runs on a production install (composer --no-dev) too.
 */
class BillingLabSeeder extends Seeder
{
    public const DOMAIN = 'lab.hostelease.test';

    protected AccountBillingService $billing;

    protected HostelService $hostels;

    /** @var list<array{0:string,1:string,2:string,3:string}> code, customer, owner login, what for */
    protected array $summary = [];

    public function run(): void
    {
        $this->billing = app(AccountBillingService::class);
        $this->hostels = app(HostelService::class);

        // The billing services lazy-load (owner, lines…) as the app does under a
        // request; local strict mode would refuse it here. Off for the seed only.
        $strict = Model::preventsLazyLoading();
        Model::preventLazyLoading(false);

        try {
            DB::transaction(function () {
                self::purge();

                $this->phaseS1();
                $this->phaseS2();
                $this->phaseS3();
                $this->phaseS4();
                $this->phaseS5();
                $this->phaseS6();
            });
        } finally {
            Carbon::setTestNow();
        }

        // Every status as of TODAY: the states above were built in the past.
        foreach (self::labAccounts() as $account) {
            $this->billing->refreshAccountAnchor($account);
        }

        Model::preventLazyLoading($strict);

        $this->command?->table(['Code', 'Customer', 'Owner login', 'Use it for'], $this->summary);
        $this->command?->info('Billing lab ready: '.count($this->summary).' customers. Password for every login: password');
    }

    // ── S1 · one ledger, one writer (09_S1_MANUAL_TESTING.md) ───────────────

    protected function phaseS1(): void
    {
        // §2 + §6 tour: every branch state on one account.
        $o = $this->owner('S1-02', 'Account Tour', 'Account 360 walkthrough: normal, behind, expired branches, a 5% discount');
        [$a] = $this->paidAccount($o, 'S1-02', ['Normal A', 'Normal B', 'Normal C'], daysLeft: 300);
        $this->coveredBranch($o, 'S1-02', 'Behind 4 Months', endsInDays: 180);
        $this->coveredBranch($o, 'S1-02', 'Expired 2 Months Ago', endsInDays: -60);
        $this->discount($a, 5, 'Lab S1-02: loyalty 5% every renewal');
        $this->coAdmin($o, 'S1-02');
        $this->staff($o, 'S1-02', 'warden');

        $o = $this->owner('S1-31', 'Cancel Tier Loss', '§3.1 operator removes a branch; 3 branches = volume tier, watch the tier-loss warning');
        $this->paidAccount($o, 'S1-31', ['Keep A', 'Keep B', 'Remove Me'], daysLeft: 300);

        $o = $this->owner('S1-32', 'Owner Requests Removal', '§3.2 owner asks to remove "Ask To Remove"; §3.6 co-admin has no Request button');
        $this->paidAccount($o, 'S1-32', ['Stays', 'Ask To Remove'], daysLeft: 300);
        $this->coAdmin($o, 'S1-32');

        $o = $this->owner('S1-33', 'Removal Already Asked', '§3.3 a request is waiting: Confirm removal or Decline');
        [, $b] = $this->paidAccount($o, 'S1-33', ['Stays', 'Asked To Remove'], daysLeft: 300);
        $this->billing->requestRemoval($b[1], 'Lab S1-33: we are closing this floor');

        $o = $this->owner('S1-34', 'Restore Cancelled', '§3.4 one branch already cancelled (still covered) — Restore it');
        [, $b] = $this->paidAccount($o, 'S1-34', ['Active', 'Cancelled Restore Me'], daysLeft: 300);
        $this->billing->cancelBranch($b[1], 'Lab S1-34: cancelled so it can be restored');

        $o = $this->owner('S1-35', 'Last Branch', '§3.5 a single branch — cancel it: "the account is now closing"');
        $this->paidAccount($o, 'S1-35', ['Only Branch'], daysLeft: 300);

        $o = $this->owner('S1-36', 'Already Closing', '§3.5 every branch cancelled — Renew all must refuse; Restore to undo');
        [, $b] = $this->paidAccount($o, 'S1-36', ['Only Branch Cancelled'], daysLeft: 300);
        $this->billing->cancelBranch($b[0], 'Lab S1-36: last branch cancelled');

        $o = $this->owner('S1-41', 'Accept Pending', '§4.1 a pending renewal (proforma) waiting — Accept payment');
        [$a] = $this->paidAccount($o, 'S1-41', ['Main'], daysLeft: 25);
        $this->billing->renewAccount($a, 'yearly', [
            'payment_status' => 'pending', 'collection' => 'offline',
            'remarks' => 'Lab S1-41: proforma waiting — accept me',
        ]);

        $o = $this->owner('S1-42', 'Void Only Order', '§4.2 one paid order is all the coverage — Void it: account becomes Expired');
        $this->paidAccount($o, 'S1-42', ['Only Branch'], daysLeft: 200);

        $o = $this->owner('S1-61', 'Suspended Quote', '§6 Rule R1: suspended, 3 branches — Renew all must still quote 3, never ₹0');
        [$a] = $this->paidAccount($o, 'S1-61', ['One', 'Two', 'Three'], daysLeft: 120);
        $this->billing->suspend($a, 'Lab S1-61: suspended for the R1 quote test');

        $o = $this->owner('S1-62', 'Expired Quote', '§6 Rule R1: expired 2 months ago, 2 branches — renewal priced for 2');
        $this->paidAccount($o, 'S1-62', ['One', 'Two'], daysLeft: -60);

        $o = $this->owner('S1-07', 'Twelve Branches', '§7 performance: Account 360 with 12 branches loads as fast as a small one');
        $this->paidAccount($o, 'S1-07', array_map(fn ($i) => 'Branch '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), range(1, 12)), daysLeft: 240);
    }

    // ── S2 · payment links (11_S2_MANUAL_TESTING.md) ─────────────────────────

    protected function phaseS2(): void
    {
        $o = $this->owner('S2-01', 'Link Renewal', '§1–4 offline vs link; create a link, share it, pay it with a test card');
        $this->paidAccount($o, 'S2-01', ['First', 'Second'], daysLeft: 60);

        $o = $this->owner('S2-51', 'Webhook Missed', '§5.1 pay a link with the webhook off, then "Check with Razorpay"');
        $this->paidAccount($o, 'S2-51', ['Main'], daysLeft: 60);

        $o = $this->owner('S2-52', 'Double Pay Guard', '§5.2 link live, then Accept (or Void): the link must be cancelled');
        $this->paidAccount($o, 'S2-52', ['Main'], daysLeft: 60);

        $o = $this->owner('S2-53', 'Cancel Reissue', '§5.3 cancel a link, re-issue it (attempt 2); §5.4 the guards');
        $this->paidAccount($o, 'S2-53', ['Main'], daysLeft: 60);

        $o = $this->owner('S2-61', 'Add To Cycle Link', '§6.1 "Behind" ends 4 months early — Add to cycle → a prorated link');
        $this->paidAccount($o, 'S2-61', ['On Date'], daysLeft: 300);
        $this->coveredBranch($o, 'S2-61', 'Behind 4 Months', endsInDays: 180);

        $o = $this->owner('S2-65', 'Align Link', '§6.5 two behind + one never paid — Align (3) → one link for all');
        $this->paidAccount($o, 'S2-65', ['On Date'], daysLeft: 300);
        $this->coveredBranch($o, 'S2-65', 'Behind 2 Months', endsInDays: 240);
        $this->coveredBranch($o, 'S2-65', 'Behind 6 Months', endsInDays: 120);
        $this->branch($o, 'S2-65', 'Never Paid');
    }

    // ── S3 · owner self-serve + everything since (15, 20–23) ───────────────

    protected function phaseS3(): void
    {
        $o = $this->owner('S3-02', 'Self Serve Renew', '15 §2 owner renews with a test card; both totals must match Account 360');
        $this->paidAccount($o, 'S3-02', ['First', 'Second'], daysLeft: 45);

        $o = $this->owner('S3-03', 'Custom Price', '15 §3 negotiated ₹8,000/yr · ₹800/mo — change it mid-checkout');
        [$a] = $this->paidAccount($o, 'S3-03', ['First', 'Second'], daysLeft: 45);
        $this->billing->setUnitPriceOverride($a, 8000, 800);

        $o = $this->owner('S3-04', 'Operator Proforma', '15 §4 we raised a yearly renewal at a negotiated amount — owner pays THAT; monthly is refused');
        [$a] = $this->paidAccount($o, 'S3-04', ['First', 'Second'], daysLeft: 30);
        $this->billing->renewAccount($a, 'yearly', [
            'payment_status' => 'pending', 'collection' => 'offline', 'amount' => 18000,
            'remarks' => 'Lab S3-04: negotiated yearly proforma (₹18,000)',
        ]);

        $o = $this->owner('S3-05', 'Mid Checkout Change', '15 §5 open checkout, remove a branch in Account 360, then pay');
        $this->paidAccount($o, 'S3-05', ['One', 'Two', 'Three'], daysLeft: 45);

        $o = $this->owner('S3-06', 'Add Branch Paid Plan', '15 §6 trial used, paid yearly — Add a branch: prorated, "Add & pay" / "pay later"');
        $this->paidAccount($o, 'S3-06', ['Main'], daysLeft: 200);

        $o = $this->owner('S3-07', 'Coadmin Read Only', '15 §7 sign in as the co-admin (…01): read-only page; staff (…02) sees no billing');
        $this->paidAccount($o, 'S3-07', ['First', 'Second'], daysLeft: 100);
        $this->coAdmin($o, 'S3-07');
        $this->staff($o, 'S3-07', 'manager');

        $o = $this->owner('S3-08', 'Suspended Owner', '15 §7.3 account on hold: no way to pay, Contact support');
        [$a] = $this->paidAccount($o, 'S3-08', ['Main'], daysLeft: 100);
        $this->billing->suspend($a, 'Lab S3-08: on hold');

        $o = $this->owner('S3-09', 'Abandoned Checkout', 'own monthly checkout left unpaid — "Change term" lets the owner pick yearly instead');
        [$a] = $this->paidAccount($o, 'S3-09', ['First', 'Second'], daysLeft: 20);
        $this->billing->renewAccount($a, 'monthly', [
            'payment_status' => 'pending', 'collection' => 'checkout',
            'remarks' => 'Online renewal started by the owner',
        ]);

        $o = $this->owner('S3-20', 'Managed Billing', 'doc 20: managed by HostelEase — owner page read-only, card says "we look after your billing"');
        [$a] = $this->paidAccount($o, 'S3-20', ['First', 'Second'], daysLeft: 20);
        $a->forceFill(['billing_mode' => BillingMode::Managed, 'billing_mode_changed_at' => now()])->save();

        $o = $this->owner('S3-21', 'Renewal Topups', 'doc 21: Renew all includes a top-up for each branch behind the date');
        $this->paidAccount($o, 'S3-21', ['On Date A', 'On Date B'], daysLeft: 40);
        $this->coveredBranch($o, 'S3-21', 'Behind 3 Months', endsInDays: -50);
        $this->branch($o, 'S3-21', 'Never Paid');

        $o = $this->owner('S3-22', 'Trial Join', 'doc 21: trial day 3 of 14 — Add a branch: it joins the trial free, same end date');
        $this->trialAccount($o, 'S3-22', ['Trial Main'], daysLeft: 11);

        $o = $this->owner('S3-23', 'Trial Ended', 'trial ended 20 days ago — the owner subscribes (yearly or monthly)');
        $this->trialAccount($o, 'S3-23', ['Trial Main'], daysLeft: -20);

        $o = $this->owner('S3-24', 'Free Renewal All', 'doc 22 + audit A5: every branch free — "Renew for free", no Razorpay');
        [$a, $b] = $this->paidAccount($o, 'S3-24', ['Free A', 'Free B'], daysLeft: 25);
        $this->billing->setFreeRenewals($a, collect($b)->pluck('id')->all(), 1);

        $o = $this->owner('S3-25', 'Free Renewal Monthly', 'audit A6: monthly gift — Monthly ₹0, Yearly full price + "free renewal is for a monthly renewal"');
        [$a, $b] = $this->paidAccount($o, 'S3-25', ['Free A', 'Free B'], daysLeft: 20, period: 'monthly');
        $this->billing->setFreeRenewals($a, collect($b)->pluck('id')->all(), 1);

        $o = $this->owner('S3-26', 'Free Renewal Partial', 'doc 22: one branch free, one paying — a real total, gift spent only when paid');
        [$a, $b] = $this->paidAccount($o, 'S3-26', ['Free', 'Paying'], daysLeft: 25);
        $this->billing->setFreeRenewals($a, [$b[0]->id], 1);

        $o = $this->owner('S3-27', 'Bring All Up To Date', 'doc 23: three branches behind — "Bring all up to date" in one payment; one alone via ⋯ → Add to plan');
        $this->paidAccount($o, 'S3-27', ['On Date'], daysLeft: 300);
        $this->coveredBranch($o, 'S3-27', 'Behind 1 Month', endsInDays: 270);
        $this->coveredBranch($o, 'S3-27', 'Behind 5 Months', endsInDays: 150);
        $this->branch($o, 'S3-27', 'Never Paid');

        $o = $this->owner('S3-28', 'Receipts History', 'doc 23: a year of payments — receipts download; free grants read "Free"');
        [$a, $b] = $this->paidAccount($o, 'S3-28', ['Original'], daysLeft: 150);
        $this->at(now()->subMonths(3), fn () => $this->billing->addBranch($a->fresh(), $this->branch($o, 'S3-28', 'Added Later'), [
            'payment_status' => 'paid', 'collection' => 'offline', 'payment_method' => 'upi', 'transaction_number' => 'LAB-S3-28-ADD',
        ]));
        $this->billing->extendRenewalDate($a->fresh(), 15, 'days', 'Lab S3-28: goodwill — 15 free days');
        $o = $this->owner('S3-29', 'Trial Free Month', 'trial + a free renewal = one FREE MONTH: Monthly ₹0 · Yearly ₹9,000 (one month off), never a free year');
        [$a, $b] = $this->trialAccount($o, 'S3-29', ['Gifted Trial'], daysLeft: 4);
        $this->billing->setFreeRenewals($a, [$b[0]->id], 1);
    }

    // ── S5 · revenue (28_S5_DESIGN.md) ──────────────────────────────────────
    //
    // The whole lab already gives the Revenue page a year of history; these six add
    // the cases it would not otherwise show.

    protected function phaseS5(): void
    {
        $o = $this->owner('S5-01', 'Advance Renewal', 'renewed a year AHEAD today: cash this month, but deferred — not MRR — until it starts');
        [$a] = $this->paidAccount($o, 'S5-01', ['Main'], daysLeft: 165);
        $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'paid', 'collection' => 'offline', 'payment_method' => 'upi', 'transaction_number' => 'LAB-S5-01-AHEAD', 'remarks' => 'Lab S5-01: renewed a year ahead']);

        $o = $this->owner('S5-02', 'Came Back', 'plan ran out 60 days ago, renewed 5 days ago: "Came back" in How MRR moved, not "New"');
        [$a] = $this->paidAccount($o, 'S5-02', ['Main'], daysLeft: -60);
        $this->at(now()->subDays(5)->setTime(10, 0), fn () => $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'paid', 'collection' => 'offline', 'payment_method' => 'upi', 'transaction_number' => 'LAB-S5-02-BACK', 'remarks' => 'Lab S5-02: came back']));

        $o = $this->owner('S5-03', 'Trial Converted', 'trial began 30 days ago, subscribed when it ended: counts as "Subscribed" in its trial month');
        [$a] = $this->trialAccount($o, 'S5-03', ['Converted'], daysLeft: -16);
        $this->at(now()->subDays(16)->setTime(11, 0), fn () => $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'paid', 'collection' => 'offline', 'payment_method' => 'upi', 'transaction_number' => 'LAB-S5-03-SUBSCRIBED', 'remarks' => 'Lab S5-03: subscribed after the trial']));

        $o = $this->owner('S5-04', 'Voided Payment', 'paid 3 days ago, then voided (cheque bounced): in NO figure — not cash, not MRR');
        [$a] = $this->paidAccount($o, 'S5-04', ['Main'], daysLeft: 362);
        $this->billing->voidOrder($a->orders()->sole(), 'Lab S5-04: cheque bounced');

        $o = $this->owner('S5-05', 'Proforma Paid Late', 'proforma raised 40 days ago, paid 3 days ago: cash THIS month (by when it was paid)');
        [$a] = $this->paidAccount($o, 'S5-05', ['Main'], daysLeft: 20);
        $order = $this->at(now()->subDays(40)->setTime(10, 0), fn () => $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'pending', 'collection' => 'offline', 'remarks' => 'Lab S5-05: proforma raised 40 days ago']));
        $this->at(now()->subDays(3)->setTime(10, 0), fn () => $this->billing->acceptOrder($order->fresh(), ['payment_method' => 'rtgs', 'transaction_number' => 'LAB-S5-05-PAID']));

        $o = $this->owner('S5-06', 'Contraction', 'had 3 branches, renewed 10 days ago with 2 ("Leaving" cancelled): "Contraction" in the month it renewed');
        [$a, $b] = $this->paidAccount($o, 'S5-06', ['Keep A', 'Keep B', 'Leaving'], daysLeft: -10);
        $this->at(now()->subDays(10)->setTime(9, 0), function () use ($a, $b) {
            $this->billing->cancelBranch($b[2], 'Lab S5-06: closing this branch');
            $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'paid', 'collection' => 'offline', 'payment_method' => 'upi', 'transaction_number' => 'LAB-S5-06-RENEWED', 'remarks' => 'Lab S5-06: renewed with two branches']);
        });

        // S5 audit (doc 29) cases.
        $o = $this->owner('S5-07', 'Stale Proforma', 'audit B2: our proforma, then the customer paid by transfer — "already covered — void it", NOT owed');
        [$a] = $this->paidAccount($o, 'S5-07', ['Main'], daysLeft: 100);
        $this->at(now()->subDays(5)->setTime(10, 0), fn () => $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'pending', 'collection' => 'offline', 'remarks' => 'Lab S5-07: proforma — then paid another way']));
        $this->at(now()->subDays(2)->setTime(10, 0), fn () => $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'paid', 'collection' => 'offline', 'payment_method' => 'rtgs', 'transaction_number' => 'LAB-S5-07-TRANSFER', 'remarks' => 'Lab S5-07: paid by bank transfer']));

        $o = $this->owner('S5-08', 'Ended On The 1st', 'audit B1: plan ended on the 1st of this month — last used LAST month, so it is last month\'s churn');
        $this->paidAccount($o, 'S5-08', ['Main'], daysLeft: -(now()->day - 1));

        $o = $this->owner('S5-09', 'Negotiated Two Years', 'audit B3: renewed a year ahead, then a branch added at a negotiated price for both years — the audit stays clean');
        [$a] = $this->paidAccount($o, 'S5-09', ['Main'], daysLeft: 200);
        $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'paid', 'collection' => 'offline', 'payment_method' => 'upi', 'transaction_number' => 'LAB-S5-09-AHEAD', 'remarks' => 'Lab S5-09: a year ahead']);
        $branch = $this->branch($o, 'S5-09', 'Added For Two Years');
        $q = $this->billing->quoteAddBranch($a->fresh(), $branch);
        $this->billing->addBranch($a->fresh(), $branch, ['payment_status' => 'paid', 'collection' => 'offline', 'payment_method' => 'upi', 'transaction_number' => 'LAB-S5-09-ADD', 'amount' => round((float) $q['prorated'] * 0.95, 2), 'remarks' => 'Lab S5-09: negotiated 5% off two years']);
    }

    // ── S4 · reminders (26_S4_MANUAL_TESTING.md) ────────────────────────────

    // ── S6 · UPI Autopay (33_S6_MANUAL_TESTING.md) ──────────────────────────
    //
    // Every account here is MONTHLY. Mandates carry lab tokens (token_LAB_…) that
    // Razorpay has never seen: they show every screen, and the daily job and Turn off
    // behave as they would with a token Razorpay refuses. A real mandate comes from
    // S6-01, in Razorpay test mode.

    protected function phaseS6(): void
    {
        $o = $this->owner('S6-01', 'Turn It On', 'monthly ₹2,000, no autopay yet: the renew sheet offers "Renew automatically every month"');
        $this->paidAccount($o, 'S6-01', ['Main', 'Annex'], daysLeft: 20, period: 'monthly');

        $o = $this->owner('S6-02', 'Autopay On', 'autopay on, renews in 12 days: "₹2,000 by UPI on …", no reminders, Turn off; its co-admin cannot turn it off');
        [$a] = $this->paidAccount($o, 'S6-02', ['Main', 'Annex'], daysLeft: 12, period: 'monthly');
        $this->mandate($a, 'S6-02');
        $this->coAdmin($o, 'S6-02');

        $o = $this->owner('S6-03', 'With The Bank', 'a debit is with the bank right now: no Pay button anywhere; Account 360 refuses accept / link / void / renew');
        [$a] = $this->paidAccount($o, 'S6-03', ['Main', 'Annex'], daysLeft: 2, period: 'monthly');
        $m = $this->mandate($a, 'S6-03');
        $this->at(now()->subDay()->setTime(7, 0), fn () => $this->attempt($a, $m, 1, 'order_LAB_S6_03'));

        $o = $this->owner('S6-04', 'First Try Failed', 'try 1 failed 2 days ago (insufficient balance): try 2 is due today — Account 360 shows the tries');
        [$a] = $this->paidAccount($o, 'S6-04', ['Main', 'Annex'], daysLeft: 1, period: 'monthly');
        $m = $this->mandate($a, 'S6-04');
        $this->at(now()->subDays(2)->setTime(7, 0), fn () => $this->failedAttempt($a, $m, 1, 'Insufficient balance'));

        $o = $this->owner('S6-05', 'All Tries Failed', 'all 3 tries failed, now in grace: "Autopay could not take this month’s payment", Pay button back, reminders back');
        [$a] = $this->paidAccount($o, 'S6-05', ['Main', 'Annex'], daysLeft: -2, period: 'monthly');
        $m = $this->mandate($a, 'S6-05');
        $this->at(now()->subDays(5)->setTime(7, 0), fn () => $this->failedAttempt($a, $m, 1, 'Insufficient balance'));
        $this->at(now()->subDays(3)->setTime(7, 0), fn () => $this->failedAttempt($a, $m, 2, 'Insufficient balance'));
        $this->at(now()->subDay()->setTime(7, 0), fn () => $this->failedAttempt($a, $m, 3, 'Debit declined by the bank'));
        $m->update(['failure_notified_for' => $a->fresh()->current_period_end->toDateString()]);

        $o = $this->owner('S6-06', 'Above The Limit', '11 branches = ₹11,000 a month, above the ₹10,000 mandate: never debited, owner asked to pay');
        [$a] = $this->paidAccount($o, 'S6-06', array_map(fn ($i) => "Wing {$i}", range(1, 11)), daysLeft: 10, period: 'monthly');
        $this->mandate($a, 'S6-06');

        $o = $this->owner('S6-07', 'Paused In UPI App', 'the customer paused the mandate in their UPI app: "Autopay is paused", reminders as usual');
        [$a] = $this->paidAccount($o, 'S6-07', ['Main'], daysLeft: 6, period: 'monthly');
        $this->mandate($a, 'S6-07', SubscriptionMandate::PAUSED);

        $o = $this->owner('S6-08', 'Turning Off', 'owner pressed Turn off, the bank has not confirmed yet: "Turning autopay off…"');
        [$a] = $this->paidAccount($o, 'S6-08', ['Main'], daysLeft: 15, period: 'monthly');
        $this->mandate($a, 'S6-08', SubscriptionMandate::CANCELLING, ['cancelled_by' => 'owner']);

        $o = $this->owner('S6-09', 'Cancelled By Customer', 'cancelled in the UPI app last week: no autopay card, the toggle is offered again; Account 360 says who cancelled');
        [$a] = $this->paidAccount($o, 'S6-09', ['Main'], daysLeft: 9, period: 'monthly');
        $this->mandate($a, 'S6-09', SubscriptionMandate::CANCELLED, ['cancelled_by' => 'customer', 'cancelled_at' => now()->subWeek()]);

        $o = $this->owner('S6-10', 'Went Yearly', 'autopay on, then renewed YEARLY: "Autopay is waiting — it pays monthly renewals only"');
        [$a] = $this->paidAccount($o, 'S6-10', ['Main'], daysLeft: 300);
        $this->mandate($a, 'S6-10');

        $o = $this->owner('S6-11', 'Operator Bill Waiting', 'autopay on, but we raised a proforma: autopay stays out, the reminders ask for the proforma');
        [$a] = $this->paidAccount($o, 'S6-11', ['Main', 'Annex'], daysLeft: 5, period: 'monthly');
        $this->mandate($a, 'S6-11');
        $this->billing->renewAccount($a->fresh(), 'monthly', ['payment_status' => 'pending', 'collection' => 'offline', 'remarks' => 'Lab S6-11: proforma from the operator']);

        $o = $this->owner('S6-12', 'Managed With Autopay', 'autopay on, then switched to managed: "Autopay is waiting", nothing debited; the owner can still Turn off');
        [$a] = $this->paidAccount($o, 'S6-12', ['Main'], daysLeft: 8, period: 'monthly');
        $this->mandate($a, 'S6-12');
        $a->forceFill(['billing_mode' => BillingMode::Managed->value])->save();

        $o = $this->owner('S6-13', 'Autopay Paid', 'last month paid by autopay: receipt shows it, Account 360 says "Auto-debit", next debit on schedule');
        [$a] = $this->paidAccount($o, 'S6-13', ['Main', 'Annex'], daysLeft: -20, period: 'monthly');
        $m = $this->mandate($a, 'S6-13');
        $this->at(now()->subDays(23)->setTime(7, 0), function () use ($a, $m) {
            $order = $this->attempt($a, $m, 1, 'order_LAB_S6_13');
            $this->at(now()->addDay()->setTime(9, 0), fn () => app(\App\Services\Billing\PaymentSettlement::class)
                ->settle($order->fresh(), 'pay_LAB_S6_13', $order->amountPaise(), 'autopay', 'lab'));
            $order->fresh()->update(['collection' => 'autopay']);
        });
    }

    /** A mandate as the bank would have confirmed it (lab token — Razorpay has never seen it). */
    protected function mandate(SubscriptionAccount $account, string $code, string $status = SubscriptionMandate::ACTIVE, array $extra = []): SubscriptionMandate
    {
        $slug = str_replace('-', '_', $code);

        return SubscriptionMandate::create(array_merge([
            'account_id' => $account->id, 'provider' => 'razorpay', 'method' => 'upi',
            'customer_id' => 'cust_LAB_'.$slug, 'token_id' => 'token_LAB_'.$slug,
            'email' => $account->owner?->email, 'contact' => $account->owner?->mobile,
            'max_amount' => (float) config('hostelease.autopay.max_amount', 10000), 'status' => $status,
            'authorised_at' => now()->subMonth(), 'created_by' => $account->owner_id,
        ], $extra));
    }

    /** An autopay attempt recorded and sent to the bank, exactly as the daily job leaves it. */
    protected function attempt(SubscriptionAccount $account, SubscriptionMandate $mandate, int $n, string $razorpayOrderId): SubscriptionOrder
    {
        $account = $account->fresh();
        $order = $this->billing->renewAccount($account, 'monthly', [
            'payment_status' => 'pending', 'collection' => 'autopay', 'remarks' => 'Autopay debit — attempt '.$n,
        ]);
        $order->forceFill([
            'autopay_due_on' => $account->current_period_end->toDateString(), 'autopay_attempt' => $n,
            'razorpay_order_id' => $razorpayOrderId,
        ])->save();

        return $order;
    }

    protected function failedAttempt(SubscriptionAccount $account, SubscriptionMandate $mandate, int $n, string $reason): void
    {
        $order = $this->attempt($account, $mandate, $n, 'order_LAB_'.$account->id.'_'.$n);
        $this->billing->voidOrder($order, 'Autopay debit failed: '.$reason);
        $mandate->update(['failure_reason' => $reason]);
    }

    protected function phaseS4(): void
    {
        $stages = [
            ['S4-01', 'Card Far 60d', 60, '§1.1 more than 30 days out — NO card'],
            ['S4-02', 'Card Calm 20d', 20, '§1.2 calm indigo card + "Remind me later"'],
            ['S4-03', 'Card Soon 6d', 6, '§1.3 amber; snooze offers only "3 days before"; the job sends the 7-day email (caught up)'],
            ['S4-04', 'Card Final 2d', 2, '§1.4 orange, no snooze — only × (back on reload)'],
            ['S4-05', 'Card Today', 0, '§1.6 "renews today"; the job sends the renewal-day email'],
            ['S4-06', 'Card Grace', -1, '§1.7 red grace card; the job sends "your plan has ended"'],
            ['S4-07', 'Expired 10d', -10, 'dashboard blocked (expired page); the job sends the expired email once'],
        ];
        foreach ($stages as [$code, $label, $days, $what]) {
            $o = $this->owner($code, $label, $what);
            $this->paidAccount($o, $code, ['Main', 'Second'], daysLeft: $days);
        }

        $o = $this->owner('S4-08', 'Trial Day One', '§8.1 14 days left of a trial — NO card yet (trial windows are 7/0)');
        $this->trialAccount($o, 'S4-08', ['Trial Main'], daysLeft: 14);

        $o = $this->owner('S4-09', 'Trial 5 Days Left', '§8.2 "Your trial ends in 5 days" + both prices');
        $this->trialAccount($o, 'S4-09', ['Trial Main'], daysLeft: 5);

        $o = $this->owner('S4-10', 'Monthly 10 Days', 'monthly plan: windows 15/7/0 — card shows from 15 days, not 30');
        $this->paidAccount($o, 'S4-10', ['Main'], daysLeft: 10, period: 'monthly');

        $o = $this->owner('S4-11', 'Managed 6 Days', '§8.3 managed: "We look after your billing", no pay button; email says we will be in touch');
        [$a] = $this->paidAccount($o, 'S4-11', ['Main'], daysLeft: 6);
        $a->forceFill(['billing_mode' => BillingMode::Managed, 'billing_mode_changed_at' => now()])->save();

        $o = $this->owner('S4-12', 'No Email 7 Days', '§4.11 + §3: no email — "Add your email" card; the job goes in-app only; red "No email" flag', email: false);
        $this->paidAccount($o, 'S4-12', ['Main'], daysLeft: 7);

        $o = $this->owner('S4-13', 'Unverified Email 7d', 'email not verified — amber flag; the reminder still emails it; "Add your email" ask', verified: false);
        $this->paidAccount($o, 'S4-13', ['Main'], daysLeft: 7);

        $o = $this->owner('S4-14', 'Snoozed Until 7d', '§2: already snoozed — the slim "quiet until" line; Show / Keep reminding me');
        [$a] = $this->paidAccount($o, 'S4-14', ['Main'], daysLeft: 20);
        RenewalReminderSnooze::create([
            'user_id' => $o->id, 'account_id' => $a->id,
            'cycle_end' => $a->fresh()->current_period_end->toDateString(),
            'until' => $a->fresh()->current_period_end->copy()->subDays(7)->toDateString(),
        ]);

        $o = $this->owner('S4-15', 'Reminder History', 'Account 360 Reminders panel: an emailed row and a failed one (2 tries); "Nudged 3 hours ago"');
        [$a] = $this->paidAccount($o, 'S4-15', ['Main'], daysLeft: 12);
        $cycle = $a->fresh()->current_period_end->toDateString();
        $log = fn (string $w, string $ch, string $st, int $tries, array $d, int $daysAgo) => SubscriptionReminderLog::create([
            'account_id' => $a->id, 'cycle_end' => $cycle, 'window' => $w, 'channel' => $ch, 'status' => $st, 'attempts' => $tries, 'detail' => $d,
        ])->forceFill(['created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)])->save();
        $log('due_30', 'email', 'sent', 1, ['in_app' => 'posted', 'email_verified' => true], 18);
        $log('due_15', 'in_app', 'failed', 2, ['in_app' => 'posted', 'error' => 'Lab S4-15: Connection could not be established with host'], 3);
        $a->forceFill(['last_nudged_at' => now()->subHours(3)])->save();

        $o = $this->owner('S4-16', 'Team Roles 5d', '§2.6–2.7: owner (…00) Review & renew · co-admin (…01) View subscription · manager (…02) nothing');
        $this->paidAccount($o, 'S4-16', ['Main'], daysLeft: 5);
        $this->coAdmin($o, 'S4-16');
        $this->staff($o, 'S4-16', 'manager');

        $o = $this->owner('S4-17', 'Free Renewal 7d', 'card: "Nothing to pay — your next renewal is free" + Renew for free');
        [$a, $b] = $this->paidAccount($o, 'S4-17', ['Free A', 'Free B'], daysLeft: 7);
        $this->billing->setFreeRenewals($a, collect($b)->pluck('id')->all(), 1);

        $o = $this->owner('S4-18', 'Team Emails', 'Settings → Users & Roles: verified / not verified / no email staff; Send verification', verified: false);
        $this->paidAccount($o, 'S4-18', ['Main'], daysLeft: 200);
        $branch = Hostel::where('owner_id', $o->id)->first();
        foreach ([['warden', 'Verified Warden', true, true], ['accountant', 'Unverified Accountant', true, false], ['viewer', 'No Email Viewer', false, false]] as $i => [$role, $label, $hasEmail, $verified]) {
            $u = $this->login('S4-18', 2 + $i, "Lab S4-18 {$label}", $role, $branch, $hasEmail, $verified);
            $u->hostels()->sync([$branch->id]);
        }
    }

    // ── building blocks ─────────────────────────────────────────────────────

    /** One customer: the owner login. */
    protected function owner(string $code, string $label, string $what, bool $email = true, bool $verified = true): User
    {
        $owner = $this->login($code, 0, "Lab {$code} {$label}", 'hostel_admin', null, $email, $verified);
        $this->summary[] = [$code, "Lab {$code} {$label}", self::digits($owner->mobile), $what];

        return $owner;
    }

    /** A login on the 70 P NN 0000 R range. */
    protected function login(string $code, int $role, string $name, string $userRole, ?Hostel $branch, bool $email = true, bool $verified = true): User
    {
        [$phase, $num] = self::codeParts($code);
        $mobile = sprintf('+9170%d%02d0000%d', $phase, $num, $role);
        $slug = strtolower($code).($role ? '-'.$role : '');

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email ? "{$slug}@".self::DOMAIN : null,
            'email_verified_at' => $email && $verified ? now() : null,
            'password' => Hash::make('password'),
            'role' => $userRole,
            'is_active' => true,
            'hostel_id' => $branch?->id,
        ])->save();

        return $user;
    }

    protected function coAdmin(User $owner, string $code): User
    {
        $ids = Hostel::where('owner_id', $owner->id)->pluck('id')->all();
        $u = $this->login($code, 1, "Lab {$code} Co-admin", 'hostel_admin', Hostel::find($ids[0]));
        $u->hostels()->sync($ids);

        return $u;
    }

    protected function staff(User $owner, string $code, string $role): User
    {
        $branch = Hostel::where('owner_id', $owner->id)->orderBy('id')->first();
        $u = $this->login($code, 2, "Lab {$code} Staff ".ucfirst($role), $role, $branch);
        $u->hostels()->sync([$branch->id]);

        return $u;
    }

    /** A branch with no coverage yet ("never paid"). */
    protected function branch(User $owner, string $code, string $label): Hostel
    {
        return $this->hostels->createBranchForOwner($owner, [
            'name' => "{$code} · {$label}",
            'city' => 'Lab City',
            'address' => "Billing lab — {$code}",
        ]);
    }

    /**
     * An account on a paid term, every branch together, ending $daysLeft from today.
     * Bought at the moment that makes it so — the ledger is real.
     *
     * @return array{0: SubscriptionAccount, 1: list<Hostel>}
     */
    protected function paidAccount(User $owner, string $code, array $labels, int $daysLeft, string $period = 'yearly'): array
    {
        $end = now()->startOfDay()->addDays($daysLeft);
        $start = $period === 'monthly' ? $end->copy()->subMonthNoOverflow() : $end->copy()->subYear();

        return $this->at($start->setTime(10, 0), function () use ($owner, $code, $labels, $period) {
            $branches = array_map(fn ($l) => $this->branch($owner, $code, $l), $labels);
            $account = $this->billing->accountFor($owner->fresh());
            $this->billing->renewAccount($account, $period, [
                'payment_status' => 'paid', 'collection' => 'offline', 'payment_method' => 'upi',
                'transaction_number' => 'LAB-'.$code, 'remarks' => "Lab {$code}: first {$period} term",
            ]);

            return [$account->fresh(), array_map(fn (Hostel $b) => $b->fresh(), $branches)];
        });
    }

    /** A running (or ended) free trial on the account's first branch. */
    protected function trialAccount(User $owner, string $code, array $labels, int $daysLeft): array
    {
        $start = now()->startOfDay()->addDays($daysLeft)->subDays((int) config('hostelease.trial_days', 14));

        return $this->at($start->setTime(10, 0), function () use ($owner, $code, $labels) {
            $branches = array_map(fn ($l) => $this->branch($owner, $code, $l), $labels);
            $this->billing->recordBranchRenewal($branches[0], 'trial', ['payment_status' => 'paid', 'remarks' => "Lab {$code}: free trial"]);

            return [$this->billing->accountFor($owner->fresh())->fresh(), $branches];
        });
    }

    /** One more branch, paid on its own a year before it ends — so it ends $endsInDays from today. */
    protected function coveredBranch(User $owner, string $code, string $label, int $endsInDays): Hostel
    {
        $end = now()->startOfDay()->addDays($endsInDays);

        return $this->at($end->copy()->subYear()->setTime(10, 0), function () use ($owner, $code, $label) {
            $b = $this->branch($owner, $code, $label);
            $this->billing->recordBranchRenewal($b, 'yearly', [
                'payment_status' => 'paid', 'collection' => 'offline', 'payment_method' => 'upi',
                'transaction_number' => 'LAB-'.$code.'-'.strtoupper(substr(md5($label), 0, 4)), 'remarks' => "Lab {$code}: {$label}",
            ]);

            return $b->fresh();
        });
    }

    protected function discount(SubscriptionAccount $account, int $percent, string $reason): void
    {
        Discount::create([
            'account_id' => $account->id, 'branch_id' => null, 'reason' => $reason,
            'recurrence' => DiscountRecurrence::EveryRenewal->value, 'type' => DiscountType::Percentage->value,
            'value' => $percent, 'status' => DiscountStatus::Active->value,
        ]);
    }

    /** Run $fn with the clock set to $when, then put it back. */
    protected function at(Carbon $when, Closure $fn): mixed
    {
        $before = Carbon::hasTestNow() ? Carbon::now() : null;
        Carbon::setTestNow($when);

        try {
            return $fn();
        } finally {
            Carbon::setTestNow($before);
        }
    }

    // ── lab bookkeeping ─────────────────────────────────────────────────────

    /** @return array{0:int,1:int} 'S1-35' → [1, 35] */
    protected static function codeParts(string $code): array
    {
        preg_match('/^S(\d)-(\d{2})$/', $code, $m);

        return [(int) $m[1], (int) $m[2]];
    }

    protected static function digits(string $mobile): string
    {
        return substr(preg_replace('/\D/', '', $mobile), -10);
    }

    /** Every login the lab owns: the 70… range, named "Lab …" — nothing else. */
    public static function labUsers()
    {
        return User::withTrashed()->where('mobile', 'like', '+9170%')->where('name', 'like', 'Lab S%');
    }

    /** @return \Illuminate\Support\Collection<int, SubscriptionAccount> */
    public static function labAccounts()
    {
        return SubscriptionAccount::withTrashed()->whereIn('owner_id', self::labUsers()->pluck('id'))->get();
    }

    /** Remove every row the lab made before. */
    public static function purge(): void
    {
        $userIds = self::labUsers()->pluck('id');
        $accountIds = SubscriptionAccount::withTrashed()->whereIn('owner_id', $userIds)->pluck('id');
        $hostelIds = Hostel::withTrashed()->whereIn('owner_id', $userIds)->pluck('id');
        $orderIds = SubscriptionOrder::withTrashed()->whereIn('account_id', $accountIds)->pluck('id');

        SubscriptionMandate::whereIn('account_id', $accountIds)->delete();
        Notification::withTrashed()->whereNull('hostel_id')->whereIn('type', ['autopay_failed', 'autopay_cancel_failed'])
            ->get()->filter(fn ($n) => in_array((int) str_replace('account:', '', (string) ($n->data['sig'] ?? '')), $accountIds->all(), true))
            ->each->forceDelete();
        DB::table('subscription_order_lines')->whereIn('order_id', $orderIds)->delete();
        SubscriptionOrder::withTrashed()->whereIn('id', $orderIds)->forceDelete();
        Discount::withTrashed()->whereIn('account_id', $accountIds)->forceDelete();
        SubscriptionReminderLog::whereIn('account_id', $accountIds)->delete();
        RenewalReminderSnooze::whereIn('account_id', $accountIds)->delete();
        Notification::withTrashed()->whereIn('hostel_id', $hostelIds)->forceDelete();
        SubscriptionAccount::withTrashed()->whereIn('id', $accountIds)->forceDelete();
        DB::table('hostel_user')->whereIn('user_id', $userIds)->orWhereIn('hostel_id', $hostelIds)->delete();
        User::withTrashed()->whereIn('id', $userIds)->update(['hostel_id' => null]);
        Hostel::withTrashed()->whereIn('id', $hostelIds)->forceDelete();
        User::withTrashed()->whereIn('id', $userIds)->forceDelete();
    }
}
