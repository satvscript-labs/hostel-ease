<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\DiscountRule;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Super Admin discount management (Phase 4b, BRD §6.7):
 *  - automatic volume tiers (DiscountRule CRUD),
 *  - an all-accounts view of manual/negotiated discounts.
 */
class DiscountController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(): View
    {
        $rules = DiscountRule::orderBy('min_quantity')->get();
        $manual = Discount::with('account.owner')->latest()->paginate(20);
        $stacking = (string) config('hostelease.discount_stacking', 'stack');

        return view('superadmin.discounts.index', compact('rules', 'manual', 'stacking'));
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $data = $this->validateRule($request);
        $rule = DiscountRule::create($data + ['active' => true]);

        $this->logger->log('subscription.update', "Added volume tier ≥{$rule->min_quantity} branches", $rule);

        return back()->with('success', 'Volume tier added.');
    }

    public function updateRule(Request $request, DiscountRule $rule): RedirectResponse
    {
        $rule->update($this->validateRule($request));
        $this->logger->log('subscription.update', "Updated volume tier #{$rule->id}", $rule);

        return back()->with('success', 'Volume tier updated.');
    }

    public function toggleRule(DiscountRule $rule): RedirectResponse
    {
        $rule->update(['active' => ! $rule->active]);
        $this->logger->log('subscription.update', ($rule->active ? 'Enabled' : 'Disabled')." volume tier #{$rule->id}", $rule);

        return back()->with('success', 'Volume tier '.($rule->active ? 'enabled' : 'disabled').'.');
    }

    public function destroyRule(DiscountRule $rule): RedirectResponse
    {
        $rule->delete();
        $this->logger->log('subscription.update', 'Deleted a volume tier', $rule);

        return back()->with('success', 'Volume tier removed.');
    }

    protected function validateRule(Request $request): array
    {
        return $request->validate([
            'min_quantity' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
