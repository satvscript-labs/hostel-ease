<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Welcomes a team member and lets them confirm their own email with one tap.
 *
 * It never carries the password: the owner hands that over once, in person. The link
 * is signed, expires, and is bound to the address it was sent to — if the owner later
 * changes the address, the old link stops working.
 */
class StaffInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public const LINK_DAYS = 7;

    public function __construct(public User $member, public string $roleLabel, public string $branches, public string $invitedBy) {}

    public static function linkFor(User $user): string
    {
        return URL::temporarySignedRoute('email.confirm', now()->addDays(self::LINK_DAYS), [
            'user' => $user->public_id,
            'hash' => sha1(mb_strtolower((string) $user->email)),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Confirm your email for :app', ['app' => config('app.name', 'HostelEase')]));
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.staff-invite',
            text: 'emails.staff-invite-text',
            with: ['link' => self::linkFor($this->member), 'days' => self::LINK_DAYS],
        );
    }
}
