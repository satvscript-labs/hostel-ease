<?php

namespace App\Enums;

/**
 * What a subscription order WAS — the charge's nature, as distinct from how the
 * money arrived (CollectionMethod). Needed before S2: the receivables worklist
 * has to exclude the kinds that are ₹0 by definition and never owed.
 */
enum OrderKind: string
{
    case Purchase = 'purchase';        // a customer's first paid term
    case Renewal = 'renewal';          // a consolidated renewal of every billable branch
    case AddBranch = 'add_branch';     // a prorated co-termination top-up for one branch
    case Align = 'align';              // prorated top-ups bringing behind branches to the anchor
    case Comp = 'comp';                // a complimentary ₹0 grant
    case Trial = 'trial';              // a ₹0 free-trial window
    case Adjustment = 'adjustment';    // an operator correction (e.g. a hand-edited coverage date)

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Renewal => 'Renewal',
            self::AddBranch => 'Branch added',
            self::Align => 'Alignment',
            self::Comp => 'Complimentary',
            self::Trial => 'Free trial',
            self::Adjustment => 'Adjustment',
        };
    }

    /**
     * Whether this kind can ever represent money owed. A comp, a trial and an
     * adjustment are ₹0 grants, so a `pending` one is not a receivable — it would
     * only inflate the worklist.
     */
    public function isChargeable(): bool
    {
        return ! in_array($this, [self::Comp, self::Trial, self::Adjustment], true);
    }
}
