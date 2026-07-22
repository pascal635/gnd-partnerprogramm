<?php

namespace App\Services\Ingest;

use App\Enums\ConversionStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MatchConfidence;
use App\Enums\MatchedBy;
use App\Models\Lead;
use App\Models\VoucherCode;
use App\Services\Commission\CommissionCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeadIngestService
{
    public function __construct(private readonly CommissionCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(array $payload): Lead
    {
        $leadId = (string) ($payload['lead_id'] ?? '');
        $email = data_get($payload, 'customer.email');

        $voucher = filled($payload['voucher_code'] ?? null)
            ? VoucherCode::where('code', $payload['voucher_code'])->first()
            : null;

        $attributes = [
            'voucher_code_id' => $voucher?->id,
            'voucher_code_raw' => $payload['voucher_code'] ?? null,
            'partner_id' => $voucher?->partner_id,
            'voucher_partner_raw' => $payload['voucher_partner'] ?? $voucher?->partner_label,
            'voucher_commission_raw' => $payload['voucher_commission'] ?? $voucher?->commission_raw,
            'customer_email' => $email,
            'customer_email_norm' => filled($email) ? mb_strtolower(trim((string) $email)) : null,
            'customer_name' => data_get($payload, 'customer.name'),
            'customer_phone' => data_get($payload, 'customer.phone'),
            'plz' => data_get($payload, 'customer.plz') ?? data_get($payload, 'property.plz'),
            'ort' => data_get($payload, 'customer.ort') ?? data_get($payload, 'property.ort'),
            'property_type' => data_get($payload, 'property.objektart'),
            'source' => LeadSource::Webhook,
            'submitted_at' => isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : now(),
            'raw_payload' => $payload,
        ];

        return DB::transaction(function () use ($leadId, $attributes) {
            $lead = Lead::where('external_lead_id', $leadId)->lockForUpdate()->first();

            if ($lead) {
                $wasStub = $lead->is_stub;
                $attributes['is_stub'] = false;
                // Never downgrade a converted lead back to "new".
                if ($lead->status !== LeadStatus::Converted) {
                    $attributes['status'] = LeadStatus::New;
                }
                $lead->update($attributes);

                if ($wasStub) {
                    // The stub is now a real lead: promote its conversion(s) to a
                    // confirmed match and (re)compute commission now terms are known.
                    foreach ($lead->conversions as $conversion) {
                        $conversion->update([
                            'matched_by' => MatchedBy::ExternalLeadId,
                            'match_confidence' => MatchConfidence::High,
                            'status' => ConversionStatus::Matched,
                        ]);
                        $this->calculator->syncForConversion($conversion);
                    }
                }

                return $lead;
            }

            $attributes['external_lead_id'] = $leadId;
            $attributes['status'] = LeadStatus::New;

            return Lead::create($attributes);
        });
    }
}
