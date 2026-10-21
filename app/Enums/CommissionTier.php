<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Direct = partner who brought the customer; Recruiter = partner who recruited that partner. */
enum CommissionTier: string implements HasColor, HasLabel
{
    case Direct = 'direct';
    case Recruiter = 'recruiter';

    public function getLabel(): string
    {
        return match ($this) {
            self::Direct => 'Direkt',
            self::Recruiter => 'Werber-Provision',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Direct => 'gray',
            self::Recruiter => 'info',
        };
    }
}
