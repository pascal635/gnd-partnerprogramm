<?php

namespace App\Models;

use App\Enums\CommissionCalcStatus;
use App\Enums\CommissionKind;
use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'commission_kind' => CommissionKind::class,
            'calc_status' => CommissionCalcStatus::class,
            'status' => CommissionStatus::class,
            'commission_rate' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conversion, $this> */
    public function conversion(): BelongsTo
    {
        return $this->belongsTo(Conversion::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<VoucherCode, $this> */
    public function voucherCode(): BelongsTo
    {
        return $this->belongsTo(VoucherCode::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
