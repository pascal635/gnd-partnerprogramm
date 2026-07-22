<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WebhookStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case Processed = 'processed';
    case Failed = 'failed';
    case IgnoredDuplicate = 'ignored_duplicate';
    case InvalidSignature = 'invalid_signature';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Empfangen',
            self::Processed => 'Verarbeitet',
            self::Failed => 'Fehlgeschlagen',
            self::IgnoredDuplicate => 'Duplikat ignoriert',
            self::InvalidSignature => 'Ungültige Signatur',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received => 'info',
            self::Processed => 'success',
            self::Failed => 'danger',
            self::IgnoredDuplicate => 'gray',
            self::InvalidSignature => 'danger',
        };
    }
}
