<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeadSource: string implements HasLabel
{
    case Webhook = 'webhook';
    case Manual = 'manual';
    case Stub = 'stub';

    public function getLabel(): string
    {
        return match ($this) {
            self::Webhook => 'Formular (Webhook)',
            self::Manual => 'Manuell',
            self::Stub => 'Platzhalter (aus Conversion)',
        };
    }
}
