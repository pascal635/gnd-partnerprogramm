<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MatchedBy: string implements HasLabel
{
    case ExternalLeadId = 'external_lead_id';
    case DealId = 'deal_id';
    case Email = 'email';
    case Customer = 'customer';
    case Manual = 'manual';
    case Unmatched = 'unmatched';

    public function getLabel(): string
    {
        return match ($this) {
            self::ExternalLeadId => 'Lead-ID',
            self::DealId => 'Deal-ID',
            self::Email => 'E-Mail',
            self::Customer => 'Bestandskunde',
            self::Manual => 'Manuell',
            self::Unmatched => 'Nicht zugeordnet',
        };
    }
}
