<?php

namespace App\Mail;

use App\Models\VoucherCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Willkommens-Mail an den Partner, sobald ein Gutscheincode angelegt wurde.
 * Wird über die (Cron-)Queue versendet – blockiert die Filament-Aktion nicht.
 */
class PartnerWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public VoucherCode $voucher) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Willkommen im Partnerprogramm von gutachten-nutzungsdauer.com',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.partner-welcome',
            with: [
                'firstName' => $this->voucher->partner?->firstName() ?? '',
                'code' => $this->voucher->code,
                'discount' => $this->voucher->discountLabel(),
            ],
        );
    }
}
