<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => LeadSource::class,
            'is_stub' => 'boolean',
            'submitted_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * Public, pseudonymous reference shown to partners (never the customer name).
     */
    public function referenceId(): string
    {
        return $this->external_lead_id ?: sprintf('GND-%06d', $this->id);
    }

    /** @return BelongsTo<VoucherCode, $this> */
    public function voucherCode(): BelongsTo
    {
        return $this->belongsTo(VoucherCode::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return HasMany<Conversion, $this> */
    public function conversions(): HasMany
    {
        return $this->hasMany(Conversion::class);
    }

    /** @return HasOne<Conversion, $this> */
    public function conversion(): HasOne
    {
        return $this->hasOne(Conversion::class)->latestOfMany();
    }
}
