<?php

namespace App\Mail;

use App\Models\SubscriptionAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Subscription lifecycle email — upcoming renewal, entered grace, or fully
 * expired. Sent by ProcessSubscriptionLifecycle; a base/secondary channel
 * (BRD §1) since most owners won't have SMS available yet and many won't
 * provide an email at all — the command already skips silently when absent.
 */
class SubscriptionReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param 'upcoming'|'grace'|'expired' $kind */
    public function __construct(
        public SubscriptionAccount $account,
        public string $kind,
        public int $daysUntil,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->resolveSubjectLine());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-reminder',
            with: [
                'account' => $this->account,
                'kind' => $this->kind,
                'daysUntil' => $this->daysUntil,
                'isTrial' => $this->account->period?->value === 'trial',
            ],
        );
    }

    protected function resolveSubjectLine(): string
    {
        $label = $this->isTrialAccount() ? 'trial' : 'subscription';

        return match ($this->kind) {
            'upcoming' => $this->daysUntil <= 0
                ? ucfirst($label).' renews today'
                : ucfirst($label)." renews in {$this->daysUntil} day(s)",
            'grace' => ucfirst($label).' has expired — grace period active',
            'expired' => ucfirst($label).' has expired',
            default => 'Subscription update',
        };
    }

    protected function isTrialAccount(): bool
    {
        return $this->account->period?->value === 'trial';
    }
}
