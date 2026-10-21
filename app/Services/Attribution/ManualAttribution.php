<?php

namespace App\Services\Attribution;

use App\Enums\AttributedVia;
use App\Enums\ConversionStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MatchConfidence;
use App\Enums\MatchedBy;
use App\Models\Conversion;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\VoucherCode;
use App\Services\Commission\CommissionCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Staff corrections ("Nachtragungen"): attribute an existing order to a
 * partner, fill in a missing deal value, or record an order by hand.
 * A manual attribution always wins over later automatic rules.
 */
class ManualAttribution
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
        private readonly CustomerResolver $customers,
    ) {}

    /** Locked once any of its commissions (direct or recruiter) is approved/paid. */
    public function isLocked(Conversion $conversion): bool
    {
        return $conversion->commissions()->get()->contains(fn ($c) => CommissionCalculator::isLocked($c));
    }

    public function assign(Conversion $conversion, Partner $partner, ?VoucherCode $voucher, bool $reassignCustomer): Conversion
    {
        if ($this->isLocked($conversion)) {
            throw new RuntimeException('Die Provision ist bereits freigegeben oder ausbezahlt.');
        }

        return DB::transaction(function () use ($conversion, $partner, $voucher, $reassignCustomer) {
            $voucher ??= $this->defaultVoucher($partner);
            $lead = $conversion->lead;

            $lead->update([
                'partner_id' => $partner->id,
                ...$this->terms($partner, $voucher),
                'attributed_via' => AttributedVia::Manual,
            ]);

            if ($lead->customer) {
                $reassignCustomer
                    ? $this->customers->reassign($lead->customer, $partner->id, $voucher?->id)
                    : $this->customers->claim($lead->customer, $partner->id, $voucher?->id, $lead->id);
            }

            $conversion->update([
                'customer_id' => $lead->customer_id,
                'matched_by' => MatchedBy::Manual,
                'match_confidence' => MatchConfidence::High,
                'status' => ConversionStatus::Matched,
            ]);

            $this->calculator->syncForConversion($conversion->refresh()->load('lead'));

            return $conversion;
        });
    }

    public function setDealValue(Conversion $conversion, float $dealValue): Conversion
    {
        if ($this->isLocked($conversion)) {
            throw new RuntimeException('Die Provision ist bereits freigegeben oder ausbezahlt.');
        }

        $conversion->update(['deal_value' => $dealValue]);
        $this->calculator->syncForConversion($conversion->refresh()->load('lead'));

        return $conversion;
    }

    /**
     * @param  array{partner_id: int, voucher_code_id?: int|null, customer_name?: string|null, customer_email?: string|null, deal_value?: float|string|null, converted_at?: mixed, deal_id?: string|null}  $data
     */
    public function createManual(array $data): Conversion
    {
        $partner = Partner::findOrFail($data['partner_id']);
        $voucher = filled($data['voucher_code_id'] ?? null)
            ? VoucherCode::find($data['voucher_code_id'])
            : $this->defaultVoucher($partner);
        $convertedAt = filled($data['converted_at'] ?? null) ? Carbon::parse($data['converted_at']) : now();
        $emailNorm = CustomerResolver::normalizeEmail($data['customer_email'] ?? null);

        return DB::transaction(function () use ($data, $partner, $voucher, $convertedAt, $emailNorm) {
            $customer = $this->customers->forEmail($emailNorm);

            $lead = Lead::create([
                'partner_id' => $partner->id,
                ...$this->terms($partner, $voucher),
                'customer_id' => $customer?->id,
                'attributed_via' => AttributedVia::Manual,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_email_norm' => $emailNorm,
                'status' => LeadStatus::Converted,
                'is_stub' => false,
                'source' => LeadSource::Manual,
                'submitted_at' => $convertedAt,
            ]);

            if ($customer) {
                $this->customers->claim($customer, $partner->id, $voucher?->id, $lead->id);
            }

            $conversion = Conversion::create([
                'external_deal_id' => filled($data['deal_id'] ?? null) ? trim((string) $data['deal_id']) : null,
                'lead_id' => $lead->id,
                'customer_id' => $customer?->id,
                'deal_value' => filled($data['deal_value'] ?? null) ? (float) $data['deal_value'] : null,
                'deal_currency' => 'EUR',
                'converted_at' => $convertedAt,
                'matched_by' => MatchedBy::Manual,
                'match_confidence' => MatchConfidence::High,
                'status' => ConversionStatus::Matched,
                'raw_payload' => ['source' => 'manual', 'user_id' => auth()->id()],
            ]);

            $this->calculator->syncForConversion($conversion->load('lead'));

            return $conversion;
        });
    }

    private function defaultVoucher(Partner $partner): ?VoucherCode
    {
        return $partner->voucherCodes()->where('is_active', true)->latest('id')->first();
    }

    /** @return array<string, mixed> */
    private function terms(Partner $partner, ?VoucherCode $voucher): array
    {
        return [
            'voucher_code_id' => $voucher?->id,
            'voucher_partner_raw' => $voucher?->partner_label ?? $partner->company_name,
            'voucher_commission_raw' => $voucher?->commission_raw ?? $partner->default_commission_raw,
        ];
    }
}
