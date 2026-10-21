<?php

namespace App\Services\Ingest;

use App\Enums\AttributedVia;
use App\Enums\ConversionStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MatchConfidence;
use App\Enums\MatchedBy;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\VoucherCode;
use App\Services\Attribution\CustomerResolver;
use App\Services\Commission\CommissionCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeadIngestService
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
        private readonly CustomerResolver $customers,
    ) {}

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
            'customer_email_norm' => CustomerResolver::normalizeEmail($email),
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

            $customer = $this->resolveCustomer($lead, $attributes['customer_email_norm']);
            $attributes['customer_id'] = $customer?->id;
            $attributes = $this->applyAttribution($attributes, $lead, $customer);

            if ($lead) {
                $wasStub = $lead->is_stub;
                $attributes['is_stub'] = false;
                // Never downgrade a converted lead back to "new".
                if ($lead->status !== LeadStatus::Converted) {
                    $attributes['status'] = LeadStatus::New;
                }
                $lead->update($attributes);

                if ($wasStub || $lead->wasChanged('partner_id')) {
                    // The stub is now a real lead (or its owner changed): confirm
                    // its conversion(s) and (re)compute commission with the terms.
                    foreach ($lead->conversions as $conversion) {
                        $conversion->update([
                            'customer_id' => $lead->customer_id,
                            'matched_by' => $conversion->matched_by === MatchedBy::Manual ? MatchedBy::Manual : MatchedBy::ExternalLeadId,
                            'match_confidence' => MatchConfidence::High,
                            'status' => ConversionStatus::Matched,
                        ]);
                        $this->calculator->syncForConversion($conversion->load('lead'));
                    }
                }
            } else {
                $attributes['external_lead_id'] = $leadId;
                $attributes['status'] = LeadStatus::New;
                $lead = Lead::create($attributes);
            }

            if ($customer) {
                $this->customers->claim($customer, $lead->partner_id, $lead->voucher_code_id, $lead->id);
            }

            return $lead;
        });
    }

    /** Email customer; merges in a person-only customer a stub may already carry. */
    private function resolveCustomer(?Lead $lead, ?string $emailNorm): ?Customer
    {
        $byEmail = $this->customers->forEmail($emailNorm);
        $existing = $lead?->customer;

        if ($existing && $byEmail && ! $existing->is($byEmail)) {
            return $this->customers->merge($byEmail, $existing);
        }

        return $byEmail ?? $existing;
    }

    /**
     * Precedence: staff decision > existing customer owner (first-touch,
     * even if another partner's code was used) > the code on this lead.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyAttribution(array $attributes, ?Lead $lead, ?Customer $customer): array
    {
        if ($lead?->attributed_via === AttributedVia::Manual) {
            unset(
                $attributes['partner_id'],
                $attributes['voucher_code_id'],
                $attributes['voucher_partner_raw'],
                $attributes['voucher_commission_raw'],
            );

            return $attributes;
        }

        if ($customer?->partner_id) {
            return array_merge($attributes, $this->customers->attributionFor($customer));
        }

        $attributes['attributed_via'] = $attributes['partner_id'] ? AttributedVia::Voucher : null;

        return $attributes;
    }
}
