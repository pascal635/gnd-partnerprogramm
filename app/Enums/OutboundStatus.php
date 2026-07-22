<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OutboundStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Ausstehend',
            self::Sent => 'Gesendet',
            self::Acknowledged => 'Bestätigt',
            self::Failed => 'Fehlgeschlagen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'info',
            self::Acknowledged => 'success',
            self::Failed => 'danger',
        };
    }
}
