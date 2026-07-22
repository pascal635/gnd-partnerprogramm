<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Payout lifecycle (no automated money movement — status tracking only). */
enum CommissionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Offen',
            self::Approved => 'Freigegeben',
            self::Paid => 'Ausgezahlt',
            self::Cancelled => 'Storniert',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Paid => 'success',
            self::Cancelled => 'gray',
        };
    }
}
