<?php

namespace App\Services\Commission;

use App\Enums\CommissionCalcStatus;
use App\Enums\CommissionKind;
use App\Enums\CommissionStatus;
use App\Models\Commission;
use App\Models\Conversion;
use App\Support\CommissionParser;

class CommissionCalculator
{
    /**
     * Create or update the commission for a conversion.
     *
     * Terms are read from the lead's snapshot first (voucher_commission_raw),
     * falling back to the linked voucher code. fix => flat amount; percent =>
     * deal_value × rate (pending_input if deal_value missing). Already
     * approved/paid commissions are locked and never recomputed.
     */
    public function syncForConversion(Conversion $conversion): ?Commission
    {
        $lead = $conversion->lead;

        if (! $lead) {
            return null;
        }

        [$kind, $rate] = $this->resolveTerms($lead);

        if ($kind === CommissionKind::None || $rate === null) {
            return null; // this code carries no partner provision
        }

        $existing = Commission::where('conversion_id', $conversion->id)->first();

        if ($existing && in_array($existing->status, [CommissionStatus::Approved, CommissionStatus::Paid], true)) {
            return $existing; // locked
        }

        $dealValue = $conversion->deal_value !== null ? (float) $conversion->deal_value : null;

        $base = null;
        $amount = null;
        $calcStatus = CommissionCalcStatus::Calculated;

        if ($kind === CommissionKind::Fix) {
            $amount = $rate;
        } else { // percent
            if ($dealValue === null) {
                $calcStatus = CommissionCalcStatus::PendingInput;
            } else {
                $base = $dealValue;
                $amount = round($dealValue * $rate / 100, 2);
            }
        }

        return Commission::updateOrCreate(
            ['conversion_id' => $conversion->id],
            [
                'lead_id' => $lead->id,
                'partner_id' => $lead->partner_id,
                'voucher_code_id' => $lead->voucher_code_id,
                'commission_kind' => $kind,
                'commission_rate' => $rate,
                'base_amount' => $base,
                'amount' => $amount,
                'currency' => $conversion->deal_currency ?? 'EUR',
                'calc_status' => $calcStatus,
            ],
        );
    }

    /**
     * @return array{0: CommissionKind, 1: float|null}
     */
    private function resolveTerms($lead): array
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
