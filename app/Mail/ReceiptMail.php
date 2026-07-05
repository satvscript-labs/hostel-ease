<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment, public string $pdf)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Receipt '.$this->payment->receipt_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.receipt',
            with: ['payment' => $this->payment],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->payment->receipt_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
