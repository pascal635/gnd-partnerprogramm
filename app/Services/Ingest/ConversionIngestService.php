<?php

namespace App\Services\Ingest;

use App\Enums\LeadStatus;
use App\Models\Conversion;
use App\Services\Commission\CommissionCalculator;
use App\Services\Matching\LeadConversionMatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConversionIngestService
{
    public function __construct(
        private readonly LeadConversionMatcher $matcher,
        private readonly CommissionCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(array $payload): Conversion
    {
        $dealId = (string) ($payload['deal_id'] ?? '');

        return DB::transaction(function () use ($dealId, $payload) {
            // Idempotent on external_deal_id — re-fires reuse the same row.
            $conversion = Conversion::updateOrCreate(
                ['external_deal_id' => $dealId],
                [
                    'external_lead_id' => $payload['lead_id'] ?? null,
                    'deal_value' => $payload['deal_value'] ?? null,
                    'deal_currency' => $payload['currency'] ?? 'EUR',
                    'converted_at' => isset($payload['converted_at']) ? Carbon::parse($payload['converted_at']) : now(),
                    'raw_payload' => $payload,
                ],
            );

            // Match to a lead (or create a stub for the conversion-before-lead race).
            $lead = $this->matcher->matchOrStub($conversion);

            if ($lead->status !== LeadStatus::Converted) {
                $lead->update(['status' => LeadStatus::Converted]);
            }

            // Compute commission (percent needs deal_value; else pending_input).
            $this->calculator->syncForConversion($conversion->refresh()->load('lead'));

            return $conversion;
        });
    }
}
