<?php

namespace App\Services\Reporting;

use App\Enums\PartnerStatus;
use App\Mail\AccountingMonthlyReportMail;
use App\Mail\PartnerMonthlyReportMail;
use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Number;

/**
 * Versendet die Monatsreports (Partner-Kurzmails + Buchhaltungs-CSV).
 * Ohne Cron: runIfDue() wird per Middleware bei Admin-Aufrufen getriggert und
 * feuert einmalig, sobald ein neuer Monat begonnen hat (Marker + Lock gegen
 * Doppelversand).
 */
class MonthlyReportSender
{
    private const MARKER = 'report.last_monthly_period';

    public function runIfDue(): void
    {
        $target = Carbon::now()->subMonthNoOverflow()->format('Y-m');
        $last = Setting::get(self::MARKER);

        // Erster Lauf: still initialisieren, keinen rückwirkenden Versand.
        if ($last === null) {
            Setting::put(self::MARKER, $target);

            return;
        }

        if ($last >= $target) {
            return;
        }

        $lock = Cache::lock('gnd-monthly-reports', 180);

        if (! $lock->get()) {
            return;
        }

        try {
            $last = Setting::get(self::MARKER);
            if ($last !== null && $last >= $target) {
                return;
            }

            $this->sendForMonth(Carbon::createFromFormat('Y-m-d', $target.'-01')->startOfMonth());
            Setting::put(self::MARKER, $target);
        } finally {
            $lock->release();
        }
    }

    /** Versendet Partner- und Buchhaltungs-Report für den Monat von $monthStart. */
    public function sendForMonth(Carbon $monthStart): void
    {
        $from = $monthStart->copy()->startOfMonth();
        $until = $monthStart->copy()->endOfMonth();
        $report = new CommissionReport($from, $until);
        $monthLabel = $from->locale('de')->translatedFormat('F Y');

        // Partner-Kurzmails an aktive Partner mit E-Mail.
        Partner::query()
            ->where('status', PartnerStatus::Active->value)
            ->whereNotNull('email')
            ->get()
            ->each(function (Partner $partner) use ($report, $monthLabel): void {
                $f = $report->figuresFor($partner->id);

                Mail::to($partner->email)->send(new PartnerMonthlyReportMail(
                    $partner->formalGreeting(),
                    $monthLabel,
                    (int) $f['ersteinschaetzungen'],
                    (int) $f['beauftragungen'],
                    (float) $f['provision'],
                ));
            });

        // Buchhaltungs-Report mit zwei CSVs.
        $to = (string) config('gnd.reports.accounting_email');

        if (filled($to)) {
            $summary = $report->partnerSummaryRows();
            $beauftragungen = (int) array_sum(array_column($summary, 'beauftragungen'));
            $provision = (float) array_sum(array_column($summary, 'provision'));

            Mail::to($to)->send(new AccountingMonthlyReportMail(
                periodLabel: $monthLabel,
                beauftragungen: $beauftragungen,
                provisionGesamt: Number::currency($provision, 'EUR', 'de'),
                detailCsv: $report->detailCsv(),
                summaryCsv: $report->summaryCsv(),
                detailFilename: "buchhaltung-detail-{$from->format('Y-m')}.csv",
                summaryFilename: "buchhaltung-summen-{$from->format('Y-m')}.csv",
            ));
        }
    }
}
