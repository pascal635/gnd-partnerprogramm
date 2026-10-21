<?php

namespace App\Models;

use App\Enums\CommissionTier;
use App\Enums\ConversionStatus;
use App\Enums\MatchConfidence;
use App\Enums\MatchedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'deal_value' => 'decimal:2',
            'converted_at' => 'datetime',
            'matched_by' => MatchedBy::class,
            'match_confidence' => MatchConfidence::class,
            'status' => ConversionStatus::class,
            'raw_payload' => 'array',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The direct commission (partner who brought the customer).
     *
     * @return HasOne<Commission, $this>
     */
    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class)->where('tier', CommissionTier::Direct->value);
    }

    /** @return HasOne<Commission, $this> */
    public function recruiterCommission(): HasOne
    {
        return $this->hasOne(Commission::class)->where('tier', CommissionTier::Recruiter->value);
    }

    /**
     * All commissions of this order (direct + recruiter).
     *
     * @return HasMany<Commission, $this>
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
