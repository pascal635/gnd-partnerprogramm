<?php

namespace App\Services\Reporting;

use App\Enums\CommissionKind;
use App\Enums\CommissionStatus;
use App\Models\Commission;
use App\Models\Conversion;
use App\Models\Lead;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Baut die Provisions-Reports für einen Zeitraum:
 * - Monat = nach Beauftragung (conversions.converted_at).
 * - Detail: eine Zeile je beauftragtem Lead (zum Prüfen).
 * - Summe: eine Zeile je Partner (zum Gutschreiben).
 * CSV im Excel-DE-Format (UTF-8 + BOM, Semikolon, Komma-Dezimal, TT.MM.JJJJ).
 */
class CommissionReport
{
    public function __construct(
        public Carbon $from,
        public Carbon $until,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function detailRows(): array
    {
        return Conversion::query()
            ->whereBetween('converted_at', [$this->from, $this->until])
            ->with(['lead.partner', 'lead.voucherCode', 'commission'])
            ->orderBy('converted_at')
            ->get()
            ->map(function (Conversion $c): array {
                $lead = $c->lead;
                $commission = $c->commission;

                return [
                    'partner' => $lead?->partner?->company_name ?? '(nicht zugeordnet)',
                    'lead_id' => $lead?->external_lead_id ?: ($lead?->referenceId() ?? ''),
                    'kunde' => $lead?->customer_name ?? '',
                    'email' => $lead?->customer_email ?? '',
                    'code' => $lead?->voucherCode?->code ?? $lead?->voucher_code_raw ?? '',
                    'submitted_at' => $lead?->submitted_at,
                    'converted_at' => $c->converted_at,
                    'deal_value' => $c->deal_value,
                    'kind' => $commission?->commission_kind,
                    'amount' => $commission?->amount,
                    'status' => $commission?->status,
                ];
            })
            ->all();
    }

    /** Kennzahlen eines Partners im Zeitraum. */
    public function figuresFor(int $partnerId): array
    {
        $ersteinschaetzungen = Lead::query()
            ->where('partner_id', $partnerId)
            ->whereBetween('submitted_at', [$this->from, $this->until])
            ->count();

        $beauftragungen = Conversion::query()
            ->whereBetween('converted_at', [$this->from, $this->until])
            ->whereHas('lead', fn (Builder $q) => $q->where('partner_id', $partnerId))
            ->count();

        $provision = (float) Commission::query()
            ->where('partner_id', $partnerId)
            ->where('status', '!=', CommissionStatus::Cancelled->value)
            ->whereHas('conversion', fn (Builder $q) => $q->whereBetween('converted_at', [$this->from, $this->until]))
            ->sum('amount');

        return compact('ersteinschaetzungen', 'beauftragungen', 'provision');
    }

    /** @return array<int, array<string, mixed>> Partner mit >= 1 Beauftragung im Zeitraum. */
    public function partnerSummaryRows(): array
    {
        $partnerIds = Conversion::query()
            ->whereBetween('conversions.converted_at', [$this->from, $this->until])
            ->join('leads', 'conversions.lead_id', '=', 'leads.id')
            ->whereNotNull('leads.partner_id')
            ->distinct()
            ->pluck('leads.partner_id');

        return Partner::query()
            ->whereIn('id', $partnerIds)
            ->orderBy('company_name')
            ->get()
            ->map(function (Partner $p): array {
                $f = $this->figuresFor($p->id);

                return [
                    'partner' => $p->company_name,
                    'contact' => $p->displayName(),
                    'email' => $p->email ?? '',
                    'ersteinschaetzungen' => $f['ersteinschaetzungen'],
                    'beauftragungen' => $f['beauftragungen'],
                    'provision' => $f['provision'],
                ];
            })
            ->all();
    }

    public function detailCsv(): string
    {
        $header = ['Partner', 'Lead-ID', 'Kunde', 'Kunde E-Mail', 'Gutscheincode', 'Ersteinschätzung am', 'Beauftragt am', 'Deal-Wert', 'Provisionsart', 'Provision', 'Status'];

        $rows = array_map(fn (array $r): array => [
            $r['partner'],
            $r['lead_id'],
            $r['kunde'],
            $r['email'],
            $r['code'],
            $r['submitted_at']?->format('d.m.Y') ?? '',
            $r['converted_at']?->format('d.m.Y') ?? '',
            $r['deal_value'] !== null ? $this->money($r['deal_value']) : '',
            $this->kindLabel($r['kind']),
            $r['amount'] !== null ? $this->money($r['amount']) : '',
            $r['status'] instanceof CommissionStatus ? $r['status']->getLabel() : '—',
        ], $this->detailRows());

        return $this->csv($header, $rows);
    }

    public function summaryCsv(): string
    {
        $header = ['Partner', 'Ansprechpartner', 'E-Mail', 'Ersteinschätzungen', 'Beauftragungen', 'Provision gesamt'];
        $data = $this->partnerSummaryRows();

        $rows = array_map(fn (array $r): array => [
            $r['partner'],
            $r['contact'],
            $r['email'],
            (string) $r['ersteinschaetzungen'],
            (string) $r['beauftragungen'],
            $this->money($r['provision']),
        ], $data);

        // Gesamt-Zeile.
        $rows[] = [
            'Gesamt',
            '',
            '',
            (string) array_sum(array_column($data, 'ersteinschaetzungen')),
            (string) array_sum(array_column($data, 'beauftragungen')),
            $this->money((float) array_sum(array_column($data, 'provision'))),
        ];

        return $this->csv($header, $rows);
    }

    private function kindLabel(?CommissionKind $kind): string
    {
        return match ($kind) {
            CommissionKind::Fix => 'Fix',
            CommissionKind::Percent => 'Prozent',
            default => '—',
        };
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, string>>  $rows
     */
    private function csv(array $header, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $header, ';');
        foreach ($rows as $row) {
            fputcsv($fh, $row, ';');
        }
        rewind($fh);
        $content = stream_get_contents($fh);
        fclose($fh);

        // BOM, damit deutsches Excel UTF-8 korrekt erkennt.
        return "\xEF\xBB\xBF".$content;
    }
}
