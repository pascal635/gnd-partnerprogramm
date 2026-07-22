<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PartnerType: string implements HasLabel
{
    case Sachverstaendiger = 'sachverstaendiger';
    case Steuerberater = 'steuerberater';
    case Makler = 'makler';
    case Sonstige = 'sonstige';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sachverstaendiger => 'Sachverständiger',
            self::Steuerberater => 'Steuerberater',
            self::Makler => 'Immobilienmakler',
            self::Sonstige => 'Sonstige',
        };
    }
}
