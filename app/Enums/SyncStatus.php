<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Outbound WordPress voucher-sync state. */
enum SyncStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Nicht synchronisiert',
            self::Synced => 'Synchronisiert',
            self::Failed => 'Fehler',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Synced => 'success',
            self::Failed => 'danger',
        };
    }
}
