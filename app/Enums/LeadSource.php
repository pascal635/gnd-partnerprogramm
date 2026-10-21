<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeadSource: string implements HasLabel
{
    case Webhook = 'webhook';
    case Manual = 'manual';
    case Stub = 'stub';
    case Repeat = 'repeat';

    public function getLabel(): string
    {
        return match ($this) {
            self::Webhook => 'Formular (Webhook)',
            self::Manual => 'Manuell',
            self::Stub => 'Platzhalter (aus Conversion)',
            self::Repeat => 'Folgeauftrag',
        };
    }

    /** Leads that are orders without an assessment form (not counted as Ersteinschätzung). */
    public static function nonAssessment(): array
    {
        return [self::Repeat->value, self::Manual->value];
    }
}
