<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PartnerType: string implements HasLabel
{
    case Sachverstaendiger = 'sachverstaendiger';
    case Steuerberater = 'steuerberater';
    case Makler = 'makler';
    case Finanzierer = 'finanzierer';
    case Versicherer = 'versicherer';
    case Influencer = 'influencer';
    case Hausverwalter = 'hausverwalter';
    case Sonstige = 'sonstige';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sachverstaendiger => 'Sachverständiger',
            self::Steuerberater => 'Steuerberater',
            self::Makler => 'Immobilienmakler',
            self::Finanzierer => 'Finanzierer',
            self::Versicherer => 'Versicherer',
            self::Influencer => 'Influencer',
            self::Hausverwalter => 'Hausverwalter',
            self::Sonstige => 'Sonstige',
        };
    }
}
