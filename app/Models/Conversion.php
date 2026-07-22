<?php

namespace App\Models;

use App\Enums\ConversionStatus;
use App\Enums\MatchConfidence;
use App\Enums\MatchedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** @return HasOne<Commission, $this> */
    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }
}
