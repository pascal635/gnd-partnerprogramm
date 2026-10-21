<?php

namespace Tests\Concerns;

use App\Models\Conversion;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\VoucherCode;
use App\Services\Ingest\ConversionIngestService;
use App\Services\Ingest\LeadIngestService;

trait CreatesPartners
{
    protected function partnerWithCode(string $name, string $code, string $commission = '10%'): array
    {
        $partner = Partner::create([
            'company_name' => $name,
            'partner_type' => 'sonstige',
            'status' => 'active',
            'email' => strtolower($code).'@example.test',
        ]);

        $percent = str_ends_with($commission, '%');

        $voucher = VoucherCode::create([
            'partner_id' => $partner->id,
            'code' => $code,
            'type' => 'prozent',
            'value' => 10,
            'partner_label' => $name,
            'commission_raw' => $commission,
            'commission_kind' => $percent ? 'percent' : 'fix',
            'commission_value' => (float) rtrim($commission, '%'),
            'is_active' => true,
            'sync_status' => 'synced',
        ]);

        return [$partner, $voucher];
    }

    protected function ingestLead(string $leadId, ?string $code, string $email): Lead
    {
        return app(LeadIngestService::class)->ingest([
            'lead_id' => $leadId,
            'voucher_code' => $code,
            'customer' => ['email' => $email, 'name' => 'Max Kunde'],
        ]);
    }

    protected function ingestConversion(string $dealId, ?string $leadId, ?string $personId, ?float $dealValue = 1000.0): Conversion
    {
        return app(ConversionIngestService::class)->ingest(array_filter([
            'deal_id' => $dealId,
            'lead_id' => $leadId,
            'person_id' => $personId,
            'deal_value' => $dealValue,
            'converted_at' => '2026-10-01T10:00:00Z',
        ], fn ($v) => $v !== null))->refresh();
    }
}
