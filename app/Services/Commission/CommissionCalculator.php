<?php

namespace App\Services\Commission;

use App\Enums\CommissionCalcStatus;
use App\Enums\CommissionKind;
use App\Enums\CommissionStatus;
use App\Enums\CommissionTier;
use App\Enums\PartnerStatus;
use App\Models\Commission;
use App\Models\Conversion;
use App\Models\Lead;
use App\Models\Setting;
use App\Support\CommissionParser;

class CommissionCalculator
{
    /** Global fallback when a recruited partner has no own recruiter rate. */
    public const RECRUITER_DEFAULT_KEY = 'commission.recruiter_default';

    public const RECRUITER_DEFAULT = '5%';

    public static function recruiterDefault(): string
    {
        return Setting::get(self::RECRUITER_DEFAULT_KEY) ?: self::RECRUITER_DEFAULT;
    }

    /**
     * Create or update the commissions for a conversion:
     *  - direct: the lead's partner, terms from the lead snapshot (or its code);
     *  - recruiter: the partner who recruited the lead's partner (one level).
     * fix => flat amount; percent => deal_value × rate (pending_input if
     * deal_value is missing). Approved/paid commissions are locked.
     * Returns the direct commission.
     */
    public function syncForConversion(Conversion $conversion): ?Commission
    {
        $lead = $conversion->lead;

        if (! $lead) {
            return null;
        }

        $direct = $this->syncDirect($conversion, $lead);
        $this->syncRecruiter($conversion, $lead);

        return $direct;
    }

    /** Approved/paid commissions are final and never recomputed or moved. */
    public static function isLocked(Commission $commission): bool
    {
        return in_array($commission->status, [CommissionStatus::Approved, CommissionStatus::Paid], true);
    }

    private function syncDirect(Conversion $conversion, Lead $lead): ?Commission
    {
        $existing = $this->existing($conversion, CommissionTier::Direct);

        if ($existing && self::isLocked($existing)) {
            return $existing;
        }

        [$kind, $rate] = $this->resolveTerms($lead);

        if ($kind === CommissionKind::None || $rate === null) {
            // No provision under the current terms (e.g. re-assigned to a
            // partner without terms): drop a stale, still-open commission.
            $existing?->delete();

            return null;
        }

        return $this->store($conversion, CommissionTier::Direct, $kind, $rate, [
            'lead_id' => $lead->id,
            'partner_id' => $lead->partner_id,
            'source_partner_id' => null,
            'voucher_code_id' => $lead->voucher_code_id,
        ]);
    }

    private function syncRecruiter(Conversion $conversion, Lead $lead): ?Commission
    {
        $existing = $this->existing($conversion, CommissionTier::Recruiter);

        if ($existing && self::isLocked($existing)) {
            return $existing;
        }

        $source = $lead->partner;
        $recruiter = $source?->recruiter;

        if (! $recruiter || $recruiter->is($source) || $recruiter->status !== PartnerStatus::Active) {
            $existing?->delete(); // relationship removed or recruiter inactive

            return null;
        }

        $parsed = CommissionParser::parse($source->recruiter_commission_raw ?: self::recruiterDefault());

        if ($parsed['kind'] === CommissionKind::None) {
            $existing?->delete();

            return null;
        }

        return $this->store($conversion, CommissionTier::Recruiter, $parsed['kind'], $parsed['value'], [
            'lead_id' => $lead->id,
            'partner_id' => $recruiter->id,
            'source_partner_id' => $source->id,
            'voucher_code_id' => null,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function store(Conversion $conversion, CommissionTier $tier, CommissionKind $kind, float $rate, array $attributes): Commission
    {
        $dealValue = $conversion->deal_value !== null ? (float) $conversion->deal_value : null;

        $base = null;
        $amount = null;
        $calcStatus = CommissionCalcStatus::Calculated;

        if ($kind === CommissionKind::Fix) {
            $amount = $rate;
        } elseif ($dealValue === null) {
            $calcStatus = CommissionCalcStatus::PendingInput;
        } else {
            $base = $dealValue;
            $amount = round($dealValue * $rate / 100, 2);
        }

        return Commission::updateOrCreate(
            ['conversion_id' => $conversion->id, 'tier' => $tier->value],
            [
                ...$attributes,
                'commission_kind' => $kind,
                'commission_rate' => $rate,
                'base_amount' => $base,
                'amount' => $amount,
                'currency' => $conversion->deal_currency ?? 'EUR',
                'calc_status' => $calcStatus,
            ],
        );
    }

    private function existing(Conversion $conversion, CommissionTier $tier): ?Commission
    {
        return Commission::where('conversion_id', $conversion->id)->where('tier', $tier->value)->first();
    }

    /**
     * @return array{0: CommissionKind, 1: float|null}
     */
    private function resolveTerms(Lead $lead): array
    {
        // 1) Snapshot captured at lead time.
        $parsed = CommissionParser::parse($lead->voucher_commission_raw);
        if ($parsed['kind'] !== CommissionKind::None) {
            return [$parsed['kind'], $parsed['value']];
        }

        // 2) Fall back to the linked voucher code's parsed terms.
        $voucher = $lead->voucherCode;
        if ($voucher && $voucher->commission_kind !== CommissionKind::None && $voucher->commission_value !== null) {
            return [$voucher->commission_kind, (float) $voucher->commission_value];
        }

        return [CommissionKind::None, null];
    }
}
