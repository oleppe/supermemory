<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LandingDemoRequestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly array $submission,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New MemoDoc demo request',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.landing-demo-request',
        );
    }
}
