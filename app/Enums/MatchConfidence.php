<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MatchConfidence: string implements HasLabel
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function getLabel(): string
    {
        return match ($this) {
            self::High => 'Hoch',
            self::Medium => 'Mittel',
            self::Low => 'Niedrig',
        };
    }
}
