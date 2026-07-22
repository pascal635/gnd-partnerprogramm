<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Discount TYP as stored in WordPress: prozent | fix. */
enum VoucherType: string implements HasLabel
{
    case Prozent = 'prozent';
    case Fix = 'fix';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prozent => 'Prozent (%)',
            self::Fix => 'Fixbetrag (€)',
        };
    }
}
