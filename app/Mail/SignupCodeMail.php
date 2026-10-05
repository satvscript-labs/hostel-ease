<?php

namespace App\Mail;

use App\Services\Auth\SignupVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The 6-digit code that verifies a new owner's email before their account exists.
 *
 * Deliberately NOT ShouldQueue: the person is waiting on the screen for it, and the
 * production queue only drains once a minute. SignupVerification sends it inline.
 */
class SignupCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $name,
    ) {}

    public function envelope(): Envelope
    {
        // The code in the subject: it shows in the notification preview, so the
        // owner can read it without opening the email at all.
        return new Envelope(subject: __(':code is your :app verification code', [
            'code' => $this->code,
            'app' => config('app.name', 'HostelEase'),
        ]));
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.signup-code',
            text: 'emails.signup-code-text',
            with: ['minutes' => SignupVerification::CODE_TTL_MINUTES],
        );
    }
}
