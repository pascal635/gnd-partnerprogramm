<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ConversionStatus: string implements HasColor, HasLabel
{
    case Matched = 'matched';
    case Unmatched = 'unmatched';
    case NeedsReview = 'needs_review';

    public function getLabel(): string
    {
        return match ($this) {
            self::Matched => 'Zugeordnet',
            self::Unmatched => 'Nicht zugeordnet',
            self::NeedsReview => 'Prüfung nötig',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Matched => 'success',
            self::Unmatched => 'warning',
            self::NeedsReview => 'danger',
        };
    }
}
