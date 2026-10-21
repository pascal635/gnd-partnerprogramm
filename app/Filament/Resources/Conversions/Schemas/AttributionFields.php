<?php

namespace App\Filament\Resources\Conversions\Schemas;

use App\Models\Partner;
use App\Models\VoucherCode;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/** Partner + (optional) voucher code whose terms apply. Shared by the order actions. */
class AttributionFields
{
    /** @return array<int, Select> */
    public static function make(): array
    {
        return [
            Select::make('partner_id')
                ->label('Partner')
                ->options(fn (): array => Partner::query()->orderBy('company_name')->pluck('company_name', 'id')->all())
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('voucher_code_id', null)),
            Select::make('voucher_code_id')
                ->label('Gutscheincode (Konditionen)')
                ->options(fn (Get $get): array => VoucherCode::query()
                    ->where('partner_id', $get('partner_id'))
                    ->orderByDesc('is_active')
                    ->pluck('code', 'id')
                    ->all())
                ->placeholder('Aktueller aktiver Code des Partners')
                ->helperText('Leer lassen = aktuellster aktiver Code des Partners.'),
        ];
    }
}
