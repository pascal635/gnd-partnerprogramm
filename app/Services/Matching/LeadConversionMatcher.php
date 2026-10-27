<?php

namespace App\Services\Matching;

use App\Enums\AttributedVia;
use App\Enums\ConversionStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MatchConfidence;
use App\Enums\MatchedBy;
use App\Models\Conversion;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\VoucherCode;
use App\Services\Attribution\CustomerResolver;
use App\Services\Attribution\LateCodeAttribution;

/**
 * Deterministic lead ↔ conversion matching. Highest confidence first:
 *  1) exact external_lead_id (and learn the customer's Pipedrive person id)
 *  2) Pipedrive person id of a known, owned customer → repeat order for the owner
 *  3) no match → create a stub lead and flag for review.
 *
 * Zapier conversions carry no PII; the Pipedrive person id is a pseudonymous
 * key, so customer recognition never needs names or emails from Zapier.
 */
class LeadConversionMatcher
{
    public function __construct(private readonly CustomerResolver $customers) {}

    public function matchOrStub(Conversion $conversion): Lead
    {
        $personId = $conversion->pipedrive_person_id;

        // 1) Exact stable lead id.
        if (filled($conversion->external_lead_id)) {
            $lead = Lead::where('external_lead_id', $conversion->external_lead_id)->first();

            if ($lead) {
                if ($personId) {
                    $this->customers->attachPerson($lead, $personId);
                }

                $conversion->update([
                    'lead_id' => $lead->id,
                    'customer_id' => $lead->refresh()->customer_id,
                    'matched_by' => MatchedBy::ExternalLeadId,
                    'match_confidence' => MatchConfidence::High,
                    'status' => ConversionStatus::Matched,
                ]);

                return $lead;
            }
        }

        // 2) Known customer (via Pipedrive person) → repeat order for its owner.
        $customer = $this->customers->forPersonId($personId);

        if ($customer?->partner_id) {
            $lead = $this->createRepeatLead($conversion, $customer);

            $conversion->update([
                'lead_id' => $lead->id,
                'customer_id' => $customer->id,
                'matched_by' => MatchedBy::Customer,
                'match_confidence' => MatchConfidence::High,
                'status' => ConversionStatus::Matched,
            ]);

            return $lead;
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
            'attributed_via' => $voucher?->partner_id ? AttributedVia::Voucher : null,
            'status' => LeadStatus::Converted,
            'is_stub' => true,
            'source' => LeadSource::Stub,
            'submitted_at' => $conversion->converted_at,
        ]);

        if ($personId) {
            $this->customers->attachPerson($lead, $personId);
        }

        $conversion->update([
            'lead_id' => $lead->id,
            'customer_id' => $lead->refresh()->customer_id,
            'matched_by' => MatchedBy::Unmatched,
            'status' => ConversionStatus::NeedsReview,
        ]);

        return $lead;
    }

    private function createRepeatLead(Conversion $conversion, Customer $customer): Lead
    {
        $first = $customer->firstLead;

        return Lead::create(array_merge($this->customers->attributionFor($customer), [
            'external_lead_id' => $conversion->external_lead_id,
            'customer_id' => $customer->id,
            'customer_email' => $first?->customer_email,
            'customer_email_norm' => $customer->email_norm,
            'customer_name' => $first?->customer_name,
            'customer_phone' => $first?->customer_phone,
            'status' => LeadStatus::Converted,
            'is_stub' => false,
            'source' => LeadSource::Repeat,
            'submitted_at' => $conversion->converted_at,
        ]));
    }

    private function resolveVoucher(Conversion $conversion): ?VoucherCode
    {
        return LateCodeAttribution::findVoucher(data_get($conversion->raw_payload, 'voucher_code'));
    }
}
