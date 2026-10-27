<?php

namespace App\Services\Attribution;

use App\Enums\AttributedVia;
use App\Models\Lead;
use App\Models\VoucherCode;
use App\Services\Commission\CommissionCalculator;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Codes that arrive late: entered afterwards in Pipedrive, or typed into the
 * WP form before the code existed in the dashboard. Only leads WITHOUT a
 * partner are touched — an existing attribution always stays (first partner
 * wins), and an owned customer still goes to its owner.
 */
class LateCodeAttribution
{
    public function __construct(
        private readonly CustomerResolver $customers,
        private readonly CommissionCalculator $calculator,
    ) {}

    public static function normalize(mixed $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : $code;
    }

    /** Codes are stored upper-case; customers type them in any case. */
    public static function findVoucher(mixed $code): ?VoucherCode
    {
        $code = self::normalize($code);

        return $code ? VoucherCode::where('code', $code)->first() : null;
    }

    /**
     * Gives a partner-less lead the partner behind $code. Remembers the raw
     * code even when it is unknown yet, so a later backfill can pick it up.
     * The caller recalculates commissions.
     */
    public function attribute(Lead $lead, mixed $code): bool
    {
        if ($lead->partner_id !== null || self::normalize($code) === null) {
            return false;
        }

        if (blank($lead->voucher_code_raw)) {
            $lead->update(['voucher_code_raw' => trim((string) $code)]);
        }

        $voucher = self::findVoucher($code);

        if (! $voucher?->partner_id) {
            return false;
        }

        $customer = $lead->customer;

        $lead->update($customer?->partner_id
            ? $this->customers->attributionFor($customer)
            : [
                'partner_id' => $voucher->partner_id,
                'voucher_code_id' => $voucher->id,
                'voucher_partner_raw' => $voucher->partner_label,
                'voucher_commission_raw' => $voucher->commission_raw,
                'attributed_via' => AttributedVia::Voucher,
            ]);

        if ($customer) {
            $this->customers->claim($customer, $lead->partner_id, $lead->voucher_code_id, $lead->id);
        }

        return true;
    }

    /** All partner-less leads that carry this code get its partner; returns the count. */
    public function backfill(VoucherCode $voucher): int
    {
        if (! $voucher->partner_id) {
            return 0;
        }

        $leads = Lead::query()
            ->whereNull('partner_id')
            ->whereRaw('UPPER(TRIM(voucher_code_raw)) = ?', [self::normalize($voucher->code)])
            ->orderBy('id')
            ->get();

        $count = 0;

        foreach ($leads as $lead) {
            DB::transaction(function () use ($lead, $voucher, &$count) {
                if (! $this->attribute($lead, $voucher->code)) {
                    return;
                }

                foreach ($lead->conversions as $conversion) {
                    $conversion->update(['customer_id' => $lead->customer_id]);
                    $this->calculator->syncForConversion($conversion->load('lead'));
                }

                $count++;
            });
        }

        return $count;
    }

    public static function notifyBackfilled(int $count, bool $always = false): void
    {
        if ($count === 0 && ! $always) {
            return;
        }

        Notification::make()
            ->title($count === 0 ? 'Keine offenen Anfragen gefunden' : "{$count} Anfrage(n) nachträglich zugeordnet")
            ->body($count === 0 ? 'Es gibt keine Anfragen ohne Partner mit einem bekannten Code.' : 'Die Provisionen der zugehörigen Aufträge wurden berechnet.')
            ->success()
            ->send();
    }

    public function backfillAll(): int
    {
        return VoucherCode::query()->whereNotNull('partner_id')->get()
            ->sum(fn (VoucherCode $voucher) => $this->backfill($voucher));
    }
}
