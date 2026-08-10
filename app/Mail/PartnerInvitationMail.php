<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Einladung ins Partner-Portal mit „Passwort festlegen"-Link.
 * Wird synchron versendet (kein Cron nötig).
 */
class PartnerInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $greeting,
        public string $setPasswordUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihr Zugang zum GND Partnerportal',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.partner-invitation',
            with: [
                'greeting' => $this->greeting,
                'url' => $this->setPasswordUrl,
            ],
        );
    }
}
