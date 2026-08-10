<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Monatlicher Buchhaltungs-Report mit zwei CSV-Anhängen (Detail + Summe).
 * Synchron versendet an die Buchhaltungs-Adresse.
 */
class AccountingMonthlyReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $periodLabel,
        public int $beauftragungen,
        public string $provisionGesamt,
        public string $detailCsv,
        public string $summaryCsv,
        public string $detailFilename,
        public string $summaryFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Provisions-Report {$this->periodLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.accounting-monthly-report',
            with: [
                'periodLabel' => $this->periodLabel,
                'beauftragungen' => $this->beauftragungen,
                'provisionGesamt' => $this->provisionGesamt,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->detailCsv, $this->detailFilename)
                ->withMime('text/csv'),
            Attachment::fromData(fn (): string => $this->summaryCsv, $this->summaryFilename)
                ->withMime('text/csv'),
        ];
    }
}
