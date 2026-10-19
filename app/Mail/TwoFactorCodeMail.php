<?php

namespace App\Mail;

use App\Services\Auth\TwoFactor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The 6-digit second step (S7.1): to sign in, or to switch email codes on.
 * Sent inline, never queued — the person is waiting for it.
 */
class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param 'login'|'setup' $purpose */
    public function __construct(public string $code, public string $name, public string $purpose = 'login') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':code is your :app sign-in code', [
            'code' => $this->code,
            'app' => config('app.name', 'HostelEase'),
        ]));
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.two-factor-code',
            text: 'emails.two-factor-code-text',
            with: ['minutes' => TwoFactor::CODE_MINUTES],
        );
    }
}
