<?php

namespace App\Filament\Resources\Commissions;

use App\Filament\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Resources\Commissions\Tables\CommissionsTable;
use App\Models\Commission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Partnerverwaltung';

    protected static ?string $navigationLabel = 'Provisionen';

    protected static ?string $modelLabel = 'Provision';

    protected static ?string $pluralModelLabel = 'Provisionen';

    protected static ?int $navigationSort = 3;

    // Commissions are system-generated (from conversions), never created by hand.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return CommissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissions::route('/'),
        ];
    }
}
