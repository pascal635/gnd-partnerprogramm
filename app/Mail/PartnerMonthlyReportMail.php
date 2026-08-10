<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Number;

/** Monatlicher Kurzreport an den Partner (Siezen, synchron versendet). */
class PartnerMonthlyReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $greeting,
        public string $monthLabel,
        public int $ersteinschaetzungen,
        public int $beauftragungen,
        public float $provision,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Ihr Partner-Report für {$this->monthLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.partner-monthly-report',
            with: [
                'greeting' => $this->greeting,
                'monthLabel' => $this->monthLabel,
                'ersteinschaetzungen' => $this->ersteinschaetzungen,
                'beauftragungen' => $this->beauftragungen,
                'provision' => Number::currency($this->provision, 'EUR', 'de'),
            ],
        );
    }
}
