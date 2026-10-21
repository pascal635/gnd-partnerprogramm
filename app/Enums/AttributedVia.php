<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Why a lead belongs to its partner. */
enum AttributedVia: string implements HasColor, HasLabel
{
    case Voucher = 'voucher';
    case Customer = 'customer';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Voucher => 'Gutscheincode',
            self::Customer => 'Bestandskunde',
            self::Manual => 'Manuell',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Voucher => 'info',
            self::Customer => 'success',
            self::Manual => 'warning',
        };
    }
}
