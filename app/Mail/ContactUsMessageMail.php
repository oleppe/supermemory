<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactUsMessageMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly array $submission,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New MemoDoc contact message',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-us-message',
        );
    }
}
