<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CommissionCalcStatus: string implements HasColor, HasLabel
{
    case PendingInput = 'pending_input';
    case Calculated = 'calculated';
    case NeedsReview = 'needs_review';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingInput => 'Berechnung ausstehend',
            self::Calculated => 'Berechnet',
            self::NeedsReview => 'Prüfung nötig',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingInput => 'warning',
            self::Calculated => 'success',
            self::NeedsReview => 'danger',
        };
    }
}
