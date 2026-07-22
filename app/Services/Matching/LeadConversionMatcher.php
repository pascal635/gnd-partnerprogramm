<?php

namespace App\Services\Matching;

use App\Enums\ConversionStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MatchConfidence;
use App\Enums\MatchedBy;
use App\Models\Conversion;
use App\Models\Lead;
use App\Models\VoucherCode;

/**
 * Deterministic lead ↔ conversion matching. Highest confidence first:
 *  1) exact external_lead_id
 *  2) (handled upstream: existing external_deal_id reuses its conversion row)
 *  3) no match → create a stub lead and flag for review.
 *
 * Email matching is intentionally NOT used for Zapier conversions (they carry
 * no PII), so an unmatched conversion goes to the review queue instead.
 */
class LeadConversionMatcher
{
    public function matchOrStub(Conversion $conversion): Lead
    {
        // 1) Exact stable lead id.
        if (filled($conversion->external_lead_id)) {
            $lead = Lead::where('external_lead_id', $conversion->external_lead_id)->first();

            if ($lead) {
                $conversion->update([
                    'lead_id' => $lead->id,
                    'matched_by' => MatchedBy::ExternalLeadId,
                    'match_confidence' => MatchConfidence::High,
                    'status' => ConversionStatus::Matched,
                ]);

                return $lead;
            }
        }

        // 3) No match → stub lead (hydrated later when the WP lead webhook arrives).
        $voucher = $this->resolveVoucher($conversion);

        $lead = Lead::create([
            'external_lead_id' => $conversion->external_lead_id,
            'voucher_code_id' => $voucher?->id,
            'voucher_code_raw' => data_get($conversion->raw_payload, 'voucher_code'),
            'partner_id' => $voucher?->partner_id,
            'voucher_partner_raw' => $voucher?->partner_label,
            'voucher_commission_raw' => $voucher?->commission_raw,
            'status' => LeadStatus::Converted,
            'is_stub' => true,
            'source' => LeadSource::Stub,
            'submitted_at' => $conversion->converted_at,
        ]);

        $conversion->update([
            'lead_id' => $lead->id,
            'matched_by' => MatchedBy::Unmatched,
            'status' => ConversionStatus::NeedsReview,
        ]);

        return $lead;
    }

    private function resolveVoucher(Conversion $conversion): ?VoucherCode
    {
        $code = data_get($conversion->raw_payload, 'voucher_code');

        return filled($code) ? VoucherCode::where('code', $code)->first() : null;
    }
}
