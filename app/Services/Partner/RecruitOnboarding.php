<?php

namespace App\Services\Partner;

use App\Enums\CommissionTier;
use App\Enums\PartnerStatus;
use App\Enums\SyncStatus;
use App\Filament\Resources\VoucherCodes\Schemas\VoucherCodeForm;
use App\Models\Commission;
use App\Models\Conversion;
use App\Models\Partner;
use App\Models\VoucherCode;
use App\Services\Commission\CommissionCalculator;
use App\Services\Voucher\VoucherProvisioning;
use App\Support\PartnerInvitation;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Subpartner management: a recruited partner is a full partner (login, own
 * code + terms) whose recruiter earns a configurable extra provision.
 */
class RecruitOnboarding
{
    public function __construct(
        private readonly VoucherProvisioning $vouchers,
        private readonly CommissionCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{partner: Partner, warnings: array<int, array{title: string, body: string}>}
     */
    public function create(Partner $recruiter, array $data): array
    {
        [$partner, $voucher] = DB::transaction(function () use ($recruiter, $data) {
            $partner = Partner::create([
                'company_name' => $data['company_name'],
                'partner_type' => $data['partner_type'] ?? 'sonstige',
                'salutation' => $data['salutation'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => PartnerStatus::Active,
                'recruited_by_partner_id' => $recruiter->id,
                'recruiter_commission_raw' => $this->rate($data['recruiter_commission_raw'] ?? null),
            ]);

            $voucher = null;

            if (! empty($data['create_voucher'])) {
                $terms = VoucherCodeForm::fillCommissionRaw([
                    'commission_kind' => $data['commission_kind'] ?? null,
                    'commission_value' => $data['commission_value'] ?? null,
                ]);

                $voucher = VoucherCode::create([
                    'partner_id' => $partner->id,
                    'code' => strtoupper(trim((string) $data['code'])),
                    'type' => $data['type'],
                    'value' => $data['value'],
                    'partner_label' => $partner->company_name,
                    'commission_raw' => $terms['commission_raw'],
                    'commission_kind' => $terms['commission_kind'],
                    'commission_value' => $terms['commission_value'],
                    'is_active' => true,
                    'sync_status' => SyncStatus::Pending->value,
                    'created_by' => auth()->id(),
                ]);
            }

            return [$partner, $voucher];
        });

        $warnings = [];

        // Invitation first: the welcome mail refers to it.
        if (! empty($data['invite'])) {
            try {
                PartnerInvitation::send($partner);
            } catch (Throwable) {
                $warnings[] = [
                    'title' => 'Einladung konnte nicht gesendet werden',
                    'body' => 'Der Subpartner wurde angelegt. Bitte die Einladung später erneut senden.',
                ];
            }
        }

        if ($voucher) {
            $warnings = [...$warnings, ...$this->vouchers->afterCreate($voucher)];
        }

        return ['partner' => $partner, 'warnings' => $warnings];
    }

    /** Makes an existing partner a recruit; optionally re-computes their open orders. */
    public function attach(Partner $recruiter, Partner $recruit, ?string $rate, bool $recalculate): int
    {
        $recruit->update([
            'recruited_by_partner_id' => $recruiter->id,
            'recruiter_commission_raw' => $this->rate($rate),
        ]);

        return $recalculate ? $this->recalculate($recruit) : 0;
    }

    /** Ends the relationship; still-open recruiter commissions are removed. */
    public function detach(Partner $recruit): void
    {
        DB::transaction(function () use ($recruit) {
            Commission::query()
                ->where('tier', CommissionTier::Recruiter->value)
                ->where('source_partner_id', $recruit->id)
                ->get()
                ->reject(fn (Commission $c) => CommissionCalculator::isLocked($c))
                ->each->delete();

            $recruit->update(['recruited_by_partner_id' => null, 'recruiter_commission_raw' => null]);
        });
    }

    /** Re-computes all orders of the recruit (locked commissions stay untouched). */
    public function recalculate(Partner $recruit): int
    {
        $count = 0;

        Conversion::query()
            ->whereHas('lead', fn ($q) => $q->where('partner_id', $recruit->id))
            ->with('lead')
            ->each(function (Conversion $conversion) use (&$count) {
                $this->calculator->syncForConversion($conversion);
                $count++;
            });

        return $count;
    }

    private function rate(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        return $raw === '' ? null : $raw;
    }
}
