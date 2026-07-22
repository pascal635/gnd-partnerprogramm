<?php

namespace Database\Seeders;

use App\Enums\CommissionCalcStatus;
use App\Enums\CommissionKind;
use App\Enums\CommissionStatus;
use App\Enums\ConversionStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MatchConfidence;
use App\Enums\MatchedBy;
use App\Enums\PartnerStatus;
use App\Enums\PartnerType;
use App\Enums\SyncStatus;
use App\Enums\VoucherType;
use App\Models\Commission;
use App\Models\Conversion;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\User;
use App\Models\VoucherCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A clean, hand-tuned showcase partner: exactly 10 Ersteinschätzungen and
 * 2 Beauftragungen worth 1.300 € each (10 % provision -> 130 € each = 260 €).
 * Idempotent: skips if the MUSTER10 code already exists.
 */
class ShowcasePartnerSeeder extends Seeder
{
    public function run(): void
    {
        if (VoucherCode::where('code', 'MUSTER10')->exists()) {
            $this->command?->warn('ShowcasePartnerSeeder: MUSTER10 existiert bereits – übersprungen.');

            return;
        }

        $partner = Partner::create([
            'company_name' => 'Muster Immobilien GmbH',
            'partner_type' => PartnerType::Makler,
            'contact_person' => 'Max Mustermann',
            'email' => 'muster@example.test',
            'phone' => '+49 89 1234567',
            'street' => 'Musterstraße 1',
            'zip' => '80331',
            'city' => 'München',
            'country' => 'DE',
            'status' => PartnerStatus::Active,
        ]);

        $user = User::updateOrCreate(
            ['email' => 'muster@example.test'],
            ['name' => 'Muster Immobilien GmbH', 'password' => Hash::make('password'), 'is_active' => true, 'partner_id' => $partner->id],
        );
        $user->syncRoles('partner');

        $voucher = VoucherCode::create([
            'partner_id' => $partner->id,
            'code' => 'MUSTER10',
            'type' => VoucherType::Prozent,
            'value' => 10,
            'partner_label' => $partner->company_name,
            'commission_raw' => '10%',
            'commission_kind' => CommissionKind::Percent,
            'commission_value' => 10,
            'is_active' => true,
            'sync_status' => SyncStatus::Synced,
            'synced_to_wp_at' => now()->subDays(40),
        ]);

        $cities = [['80331', 'München'], ['85221', 'Dachau'], ['82031', 'Grünwald'], ['80798', 'München']];
        $objektarten = ['Mehrfamilienhaus', 'Einfamilienhaus', 'Eigentumswohnung', 'Gewerbeobjekt'];

        for ($i = 1; $i <= 10; $i++) {
            [$plz, $ort] = $cities[($i - 1) % count($cities)];
            $submittedAt = now()->subDays(60 - $i * 5);       // spread over ~2 months
            $converted = $i <= 2;                              // first 2 are beauftragt

            $lead = Lead::create([
                'external_lead_id' => sprintf('GND-2026-9000%02d', $i),
                'voucher_code_id' => $voucher->id,
                'voucher_code_raw' => $voucher->code,
                'partner_id' => $partner->id,
                'voucher_partner_raw' => $voucher->partner_label,
                'voucher_commission_raw' => $voucher->commission_raw,
                'customer_email' => "kunde{$i}@example.test",
                'customer_email_norm' => "kunde{$i}@example.test",
                'customer_name' => "Kunde {$i}",
                'customer_phone' => '+49 170 000000'.$i,
                'property_type' => $objektarten[($i - 1) % count($objektarten)],
                'plz' => $plz,
                'ort' => $ort,
                'status' => $converted ? LeadStatus::Converted : LeadStatus::New,
                'source' => LeadSource::Webhook,
                'submitted_at' => $submittedAt,
            ]);

            if (! $converted) {
                continue;
            }

            $convertedAt = (clone $submittedAt)->addDays(18);

            $conversion = Conversion::create([
                'lead_id' => $lead->id,
                'external_deal_id' => 'pd_muster_'.$i,
                'external_lead_id' => $lead->external_lead_id,
                'customer_email_norm' => $lead->customer_email_norm,
                'deal_value' => 1300,
                'deal_currency' => 'EUR',
                'converted_at' => $convertedAt,
                'matched_by' => MatchedBy::ExternalLeadId,
                'match_confidence' => MatchConfidence::High,
                'status' => ConversionStatus::Matched,
            ]);

            Commission::create([
                'conversion_id' => $conversion->id,
                'lead_id' => $lead->id,
                'partner_id' => $partner->id,
                'voucher_code_id' => $voucher->id,
                'commission_kind' => CommissionKind::Percent,
                'commission_rate' => 10,
                'base_amount' => 1300,
                'amount' => 130,                               // 10 % of 1.300 €
                'currency' => 'EUR',
                'calc_status' => CommissionCalcStatus::Calculated,
                'status' => $i === 1 ? CommissionStatus::Paid : CommissionStatus::Pending,
                'approved_at' => $convertedAt,
                'paid_at' => $i === 1 ? (clone $convertedAt)->addDays(14) : null,
            ]);
        }

        $this->command?->info('ShowcasePartnerSeeder: Muster Immobilien GmbH mit 10 Leads / 2 Beauftragungen (260 € Provision) angelegt.');
    }
}
