<?php

namespace App\Enums;

/**
 * WHO handles an account's billing — set per account by the operator on Account 360.
 *
 *   SelfServe  the owner renews, adds branches and pays online from their own
 *              Subscription page. The default, for every new account.
 *   Managed    HostelEase does it for them: their page reads "Managed by HostelEase",
 *              and they cannot START a charge. They can still pay a link we send and
 *              ask to remove a branch, and a payment already in progress still lands.
 *
 * This is a per-customer choice, and it sits UNDER the platform-wide kill switch
 * (config hostelease.owner_self_serve): when that is off, nobody self-serves,
 * whatever their mode. See SubscriptionAccount::selfServeEnabled().
 *
 * Nothing on the owner's side can change it. Deliberately: an owner-facing "ask
 * HostelEase to manage my billing" was considered and rejected (2026-10-05) — offered
 * to everyone, everyone takes it.
 */
enum BillingMode: string
{
    case SelfServe = 'self_serve';
    case Managed = 'managed';

    public function label(): string
    {
        return match ($this) {
            self::SelfServe => 'Self-serve',
            self::Managed => 'Managed by HostelEase',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SelfServe => 'user-check',
            self::Managed => 'shield-halved',
        };
    }
}
