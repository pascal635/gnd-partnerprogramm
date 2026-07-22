<?php

namespace App\Models;

use App\Enums\CommissionKind;
use App\Enums\SyncStatus;
use App\Enums\VoucherType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherCode extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'commission_kind' => CommissionKind::class,
            'sync_status' => SyncStatus::class,
            'value' => 'decimal:2',
            'commission_value' => 'decimal:2',
            'is_active' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'synced_to_wp_at' => 'datetime',
        ];
    }

    /**
     * Rebuild the exact WordPress line: CODE;TYP;WERT;PARTNER;PROVISION.
     * Trailing empty fields keep their positional semicolons.
     */
    public function voucherLine(): string
    {
        // Trim trailing zeros from the decimal WERT: 10.00 -> "10", 10.50 -> "10.5".
        $wert = rtrim(rtrim(number_format((float) $this->value, 2, '.', ''), '0'), '.');

        return implode(';', [
            $this->code,
            $this->type->value,
            $wert,
            $this->partner_label ?? '',
            $this->commission_raw ?? '',
        ]);
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<OutboundWebhook, $this> */
    public function outboundWebhooks(): HasMany
    {
        return $this->hasMany(OutboundWebhook::class);
    }
}
