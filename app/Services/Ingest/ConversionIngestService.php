<?php

namespace App\Services\Ingest;

use App\Enums\LeadStatus;
use App\Enums\MatchedBy;
use App\Models\Conversion;
use App\Services\Attribution\CustomerResolver;
use App\Services\Attribution\LateCodeAttribution;
use App\Services\Commission\CommissionCalculator;
use App\Services\Matching\LeadConversionMatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConversionIngestService
{
    public function __construct(
        private readonly LeadConversionMatcher $matcher,
        private readonly CommissionCalculator $calculator,
        private readonly LateCodeAttribution $lateCodes,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(array $payload): Conversion
    {
        $dealId = (string) ($payload['deal_id'] ?? '');
        $personId = CustomerResolver::normalizePersonId($payload['person_id'] ?? null);

        return DB::transaction(function () use ($dealId, $payload, $personId) {
            $attributes = [
                'external_lead_id' => $payload['lead_id'] ?? null,
                'pipedrive_person_id' => $personId,
                'deal_value' => $payload['deal_value'] ?? null,
                'deal_currency' => $payload['currency'] ?? 'EUR',
                'converted_at' => isset($payload['converted_at']) ? Carbon::parse($payload['converted_at']) : now(),
                'raw_payload' => $payload,
            ];

            $existing = Conversion::where('external_deal_id', $dealId)->lockForUpdate()->first();

            // A deal staff already attributed by hand keeps its partner/lead;
            // the webhook may only refresh the deal facts.
            if ($existing?->matched_by === MatchedBy::Manual) {
                $existing->update([
                    'pipedrive_person_id' => $personId ?? $existing->pipedrive_person_id,
                    'deal_value' => $attributes['deal_value'] ?? $existing->deal_value,
                    'converted_at' => $attributes['converted_at'],
                    'raw_payload' => $payload,
                ]);
                $this->calculator->syncForConversion($existing->refresh()->load('lead'));

                return $existing;
            }

            // Idempotent on external_deal_id — re-fires reuse the same row.
            $conversion = Conversion::updateOrCreate(['external_deal_id' => $dealId], $attributes);

            // Match to a lead (repeat order of a known customer, or a stub).
            $lead = $this->matcher->matchOrStub($conversion);

            if ($lead->status !== LeadStatus::Converted) {
                $lead->update(['status' => LeadStatus::Converted]);
            }

            // Code entered afterwards in Pipedrive: only fills a missing partner.
            $this->lateCodes->attribute($lead, $payload['voucher_code'] ?? null);

            // Compute commission (percent needs deal_value; else pending_input).
            $this->calculator->syncForConversion($conversion->refresh()->load('lead'));

            return $conversion;
        });
    }
}
