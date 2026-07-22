<?php

namespace App\Models;

use App\Enums\OutboundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundWebhook extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OutboundStatus::class,
            'payload' => 'array',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<VoucherCode, $this> */
    public function voucherCode(): BelongsTo
    {
        return $this->belongsTo(VoucherCode::class);
    }
}
