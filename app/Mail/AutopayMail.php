<?php

namespace App\Mail;

use App\Models\SubscriptionAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Autopay emails (S6 · 31_S6_DESIGN.md §7). Sent inline by AutopayService.
 *
 *  debiting   — a debit was raised; Razorpay will notify and take it within about a day
 *  failed     — every attempt failed; pay here
 *  over_limit — this month is above the mandate's limit; nothing was debited; pay here
 */
class AutopayMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SubscriptionAccount $account,
        public string $kind,
        public float $amount,
        public Carbon $renewsOn,
    ) {}

    public function envelope(): Envelope
    {
        $money = hostelease_money($this->amount);

        return new Envelope(subject: match ($this->kind) {
            'debiting' => __(':amount will be debited by UPI Autopay', ['amount' => $money]),
            'over_limit' => __('This month is above your autopay limit — please pay :amount', ['amount' => $money]),
            default => __('Autopay could not take :amount — please pay', ['amount' => $money]),
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.autopay', with: [
            'name' => $this->account->owner?->name ?? __('there'),
            'kind' => $this->kind,
            'amount' => hostelease_money($this->amount),
            'renewsOn' => $this->renewsOn->translatedFormat('d M Y'),
            'payUrl' => route('admin.subscription.index'),
        ]);
    }
}
