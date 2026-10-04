<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Services\ActivityLogger;
use App\Services\Billing\AccountBillingService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The owner's branch actions that are NOT billing: rename, and asking for a branch
 * to be removed (D11).
 *
 * S3 removed the rest of this controller. It used to hold a second, older billing
 * surface: `store` (create a branch on a free trial, with no way to pay for it) and
 * `createOrder` / `verify` (renew ONE branch by a full term from its own end, at LIST
 * price — ignoring the account's negotiated price, its discounts and co-termination,
 * directly under a Settings header that says "All branches renew together"). All of
 * that now goes through the one owner billing page, Admin\SubscriptionController, on
 * the same core as the operator's Account 360.
 * See _artifact/saas_billing_autopay/14_S3_DESIGN.md.
 */
class BranchManagerController extends Controller
{
    public function __construct(
        protected AccountBillingService $accountBilling,
        protected ActivityLogger $logger,
        protected NotificationService $notifications,
    ) {}

    /**
     * Rename a branch (W9 — owners previously couldn't rename their own
     * branches anywhere). Deliberately name-and-address ONLY: status, owner,
     * billing and subscription fields belong to Super Admin, and this route
     * must never grow into a side door for them.
     */
    public function rename(Request $request, Hostel $hostel): RedirectResponse
    {
        // Only the ACCOUNT OWNER of this branch may rename it — not co-admins,
        // not staff, and never another account's owner.
        abort_unless($hostel->owner_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $old = $hostel->name;
        $hostel->update($data);

        $this->logger->log('branch.rename', "Branch renamed — '{$old}' → '{$hostel->name}'", $hostel);

        return redirect()->route('admin.settings.index', ['tab' => 'branches'])
            ->with('success', 'Branch details updated.');
    }

    /**
     * The owner ASKS for a branch to be removed (D11). They cannot cancel it
     * themselves — removal changes what they are billed, and for a hands-on
     * business the request is the retention conversation. Nothing billing-related
     * changes here: the branch stays counted, charged and working until the Super
     * Admin confirms.
     *
     * Deliberately NOT behind the owner_self_serve lock: asking is not a billing
     * operation, and an owner must always be able to start the conversation.
     */
    public function requestRemoval(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // A posted DB reference, so an integer (standards §1.1 rule 3): the modal
            // is shared across branch rows, and a URL built in the browser is the one
            // that fails silently (rule 2).
            'branch_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $hostel = Hostel::findOr((int) $data['branch_id'], fn () => abort(404));

        // The ACCOUNT OWNER only — not co-admins (who share the hostel_admin role),
        // not staff. Same rule as rename(): a 404 rather than a 403, so the
        // existence of another account's branch is never confirmed.
        abort_unless($hostel->owner_id === $request->user()->id, 404);

        if ($hostel->isCancelled()) {
            return back()->with('info', "{$hostel->name} is already scheduled for removal.");
        }

        if (! $this->accountBilling->requestRemoval($hostel, $data['reason'])) {
            return back()->with('info', 'We already have your request for this branch — our team will be in touch.');
        }

        $this->logger->log('branch.removal_requested', "Removal requested for {$hostel->name} — {$data['reason']}", $hostel);

        // Super Admin feed (hostel_id = null) so it lands on the operator's worklist.
        $this->notifications->push(
            null,
            'branch_removal_request',
            'branch_removal_request:'.$hostel->id,
            'Branch removal requested — '.$hostel->name,
            ($request->user()->name ?? 'The owner')." asked to remove {$hostel->name}. Reason: {$data['reason']}",
            'warning',
        );

        return back()->with('success', 'Request received — our team will contact you before anything changes. Nothing has been cancelled yet.');
    }

    /** The owner changes their mind. Leaves no billing trace. */
    public function withdrawRemoval(Request $request, Hostel $hostel): RedirectResponse
    {
        abort_unless($hostel->owner_id === $request->user()->id, 404);

        if (! $this->accountBilling->clearRemovalRequest($hostel)) {
            return back()->with('info', 'There is no open removal request for that branch.');
        }

        $this->logger->log('branch.removal_withdrawn', "Removal request withdrawn for {$hostel->name}", $hostel);
        $this->notifications->clear(null, 'branch_removal_request', 'branch_removal_request:'.$hostel->id);

        return back()->with('success', 'Request withdrawn — nothing changes.');
    }
}
