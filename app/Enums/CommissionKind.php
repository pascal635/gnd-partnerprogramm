<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Parsed PROVISION kind. */
enum CommissionKind: string implements HasLabel
{
    case Fix = 'fix';
    case Percent = 'percent';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fix => 'Fixbetrag (€)',
            self::Percent => 'Prozent vom Verkauf (%)',
            self::None => 'Keine Provision',
        };
    }
}
