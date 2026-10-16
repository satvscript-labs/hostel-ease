<?php

namespace App\Mail;

use App\Models\SubscriptionAccount;
use App\Services\Billing\RenewalReminders;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A renewal reminder — one per window per cycle (S4 · 24_S4_DESIGN.md §5).
 *
 * Deliberately NOT queued: the daily job sends it inline so the reminder log records
 * whether it actually left (R4). Everything it says — dates, the amount, the link,
 * the next step — comes from RenewalReminders::context(), the same source as the
 * dashboard card and the operator's WhatsApp nudge.
 */
class SubscriptionReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string, mixed> */
    public array $context;

    /** @param string $window due_N | grace | expired */
    public function __construct(
        public SubscriptionAccount $account,
        public string $window,
    ) {
        $this->context = app(RenewalReminders::class)->context($account);
    }

    public function kind(): string
    {
        return str_starts_with($this->window, 'due_') ? 'upcoming' : $this->window;
    }

    public function daysUntil(): int
    {
        return (int) $this->account->daysUntilAnchor();
    }

    public function envelope(): Envelope
    {
        $noun = $this->context['is_trial'] ? __('trial') : __('plan');
        $days = $this->daysUntil();

        $subject = match ($this->kind()) {
            'upcoming' => $days <= 0
                ? __('Your :noun renews today', ['noun' => $noun])
                : trans_choice('Your :noun renews in :n day|Your :noun renews in :n days', $days, ['noun' => $noun, 'n' => $days]),
            'grace' => __('Your :noun has ended — renew by :date to keep access', ['noun' => $noun, 'date' => $this->context['grace_until']->format('d M')]),
            'expired' => __('Your :noun has expired — access is paused', ['noun' => $noun]),
            default => __('About your :app plan', ['app' => config('app.name', 'HostelEase')]),
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-reminder',
            with: [
                'account' => $this->account,
                'kind' => $this->kind(),
                'daysUntil' => $this->daysUntil(),
                'c' => $this->context,
                'supportEmail' => config('hostelease.company.email'),
            ],
        );
    }
}
