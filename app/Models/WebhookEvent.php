<?php

namespace App\Models;

use App\Enums\WebhookSource;
use App\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    // Uses explicit received_at / processed_at instead of created/updated.
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source' => WebhookSource::class,
            'status' => WebhookStatus::class,
            'signature_valid' => 'boolean',
            'headers' => 'array',
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
