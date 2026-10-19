<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your sign-in security changed" (S7.1): two-factor switched on or off, reset by an
 * admin, or a recovery code used. If it was not them, they know at once.
 */
class SecurityAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param 'enabled'|'disabled'|'reset'|'recovery_used' $kind */
    public function __construct(public string $name, public string $kind, public ?string $ip = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            'enabled' => __('Two-factor sign-in is on'),
            'disabled' => __('Two-factor sign-in was turned off'),
            'reset' => __('Your two-factor sign-in was reset'),
            default => __('A recovery code was used to sign in'),
        });
    }

    public function content(): Content
    {
        return new Content(html: 'emails.security-alert', with: ['when' => now()->translatedFormat('d M Y, h:i A')]);
    }
}
