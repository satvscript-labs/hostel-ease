<?php

namespace App\Mail;

use App\Services\Auth\SignupVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The 6-digit code that verifies an email from the profile. Sent inline, never queued. */
class EmailCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':code is your :app verification code', [
            'code' => $this->code,
            'app' => config('app.name', 'HostelEase'),
        ]));
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.email-code',
            text: 'emails.email-code-text',
            with: ['minutes' => SignupVerification::CODE_TTL_MINUTES],
        );
    }
}
