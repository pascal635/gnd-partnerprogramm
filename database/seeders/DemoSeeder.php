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

class DemoSeeder extends Seeder
{
    private array $cities = [
        ['80331', 'München'], ['10115', 'Berlin'], ['20095', 'Hamburg'],
        ['50667', 'Köln'], ['60311', 'Frankfurt'], ['70173', 'Stuttgart'],
    ];

    private array $objektarten = ['Mehrfamilienhaus', 'Einfamilienhaus', 'Eigentumswohnung', 'Gewerbeobjekt'];

    public function run(): void
    {
        // Additional staff member (employee role).
        $employee = User::updateOrCreate(
            ['email' => 'mitarbeiter@gnd.test'],
            ['name' => 'Team Mitarbeiter', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $employee->syncRoles('employee');

        // Three partners with different commission structures.
        $definitions = [
            ['Sachverständigenbüro Müller GmbH', PartnerType::Sachverstaendiger, 'MUELLER10', VoucherType::Prozent, 10, '150', CommissionKind::Fix, 150],
            ['Steuerkanzlei Schmidt', PartnerType::Steuerberater, 'SCHMIDT', VoucherType::Fix, 100, '10%', CommissionKind::Percent, 10],
            ['Immobilien Wagner e.K.', PartnerType::Makler, 'WAGNER5', VoucherType::Prozent, 5, '5%', CommissionKind::Percent, 5],
        ];

        $refCounter = 100;

        foreach ($definitions as $i => [$company, $type, $code, $vtype, $wert, $provRaw, $kind, $kindValue]) {
            $partner = Partner::create([
                'company_name' => $company,
                'partner_type' => $type,
                'contact_person' => fake()->name(),
                'email' => 'partner'.($i + 1).'@example.test',
                'phone' => fake()->phoneNumber(),
                'city' => $this->cities[$i][1],
                'zip' => $this->cities[$i][0],
                'country' => 'DE',
                'status' => PartnerStatus::Active,
            ]);

            $partnerUser = User::updateOrCreate(
                ['email' => 'partner'.($i + 1).'@example.test'],
                ['name' => $company, 'password' => Hash::make('password'), 'is_active' => true, 'partner_id' => $partner->id],
            );
            $partnerUser->syncRoles('partner');

            $voucher = VoucherCode::create([
                'partner_id' => $partner->id,
                'code' => $code,
                'type' => $vtype,
                'value' => $wert,
                'partner_label' => $partner->company_name,
                'commission_raw' => $provRaw,
                'commission_kind' => $kind,
                'commission_value' => $kindValue,
                'is_active' => true,
                'sync_status' => SyncStatus::Synced,
                'synced_to_wp_at' => now()->subDays(30),
            ]);

            // 4 leads per partner: 2 converted, 2 still open, spread over ~8 weeks.
            for ($n = 0; $n < 4; $n++) {
                $refCounter++;
                [$plz, $ort] = $this->cities[array_rand($this->cities)];
                $submittedAt = now()->subDays(fake()->numberBetween(2, 56));
                $converted = $n < 2;

                $lead = Lead::create([
                    'external_lead_id' => sprintf('GND-2026-%06d', $refCounter),
                    'voucher_code_id' => $voucher->id,
                    'voucher_code_raw' => $voucher->code,
                    'partner_id' => $partner->id,
                    'voucher_partner_raw' => $voucher->partner_label,
                    'voucher_commission_raw' => $voucher->commission_raw,
                    'customer_email' => fake()->safeEmail(),
                    'customer_email_norm' => null,
                    'customer_name' => fake()->name(),
                    'customer_phone' => fake()->phoneNumber(),
                    'property_type' => $this->objektarten[array_rand($this->objektarten)],
                    'plz' => $plz,
                    'ort' => $ort,
                    'status' => $converted ? LeadStatus::Converted : LeadStatus::New,
                    'source' => LeadSource::Webhook,
                    'submitted_at' => $submittedAt,
                ]);
                $lead->update(['customer_email_norm' => mb_strtolower($lead->customer_email)]);

                if (! $converted) {
                    continue;
                }

                $dealValue = fake()->randomElement([690, 790, 890, 990, 1200]);
                $convertedAt = (clone $submittedAt)->addDays(fake()->numberBetween(5, 30));

                $conversion = Conversion::create([
                    'lead_id' => $lead->id,
                    'external_deal_id' => 'pd_'.fake()->unique()->numberBetween(10000, 99999),
                    'external_lead_id' => $lead->external_lead_id,
                    'customer_email_norm' => $lead->customer_email_norm,
                    'deal_value' => $dealValue,
                    'deal_currency' => 'EUR',
                    'converted_at' => $convertedAt,
                    'matched_by' => MatchedBy::ExternalLeadId,
                    'match_confidence' => MatchConfidence::High,
                    'status' => ConversionStatus::Matched,
                ]);

                $amount = $kind === CommissionKind::Fix ? $kindValue : round($dealValue * $kindValue / 100, 2);

                Commission::create([
                    'conversion_id' => $conversion->id,
                    'lead_id' => $lead->id,
                    'partner_id' => $partner->id,
                    'voucher_code_id' => $voucher->id,
                    'commission_kind' => $kind,
                    'commission_rate' => $kindValue,
                    'base_amount' => $kind === CommissionKind::Percent ? $dealValue : null,
                    'amount' => $amount,
                    'currency' => 'EUR',
                    'calc_status' => CommissionCalcStatus::Calculated,
                    'status' => $n === 0 ? CommissionStatus::Paid : CommissionStatus::Pending,
                    'approved_at' => $n === 0 ? $convertedAt : null,
                    'paid_at' => $n === 0 ? (clone $convertedAt)->addDays(14) : null,
                ]);
            }
        }
    }
}
