<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginLinkMail extends Mailable
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
            subject: 'Your sign-in link',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.login-link',
            with: ['url' => $this->url],
        );
    }
}
