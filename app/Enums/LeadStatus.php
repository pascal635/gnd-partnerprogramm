<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeadStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Converted = 'converted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Eingegangen',
            self::Converted => 'Beauftragt',
            self::Rejected => 'Storniert',
            self::Expired => 'Abgelaufen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Converted => 'success',
            self::Rejected => 'danger',
            self::Expired => 'gray',
        };
    }
}
