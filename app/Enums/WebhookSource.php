<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WebhookSource: string implements HasLabel
{
    case WpLead = 'wp_lead';
    case ZapierConversion = 'zapier_conversion';
    case WpSyncAck = 'wp_sync_ack';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::WpLead => 'WordPress Lead',
            self::ZapierConversion => 'Zapier Conversion',
            self::WpSyncAck => 'WP Sync-Bestätigung',
            self::Other => 'Sonstige',
        };
    }
}
