<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the NEW address when someone changes their account email. Until the
 * link is used, the old address still signs them in.
 */
class EmailChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url) {}

    /**
     * The subject is fixed copy, not the app name: the sender name already
     * carries the brand, and an unset APP_NAME must never leak into an inbox.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your new email address',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.email-change',
            with: ['url' => $this->url],
        );
    }
}
