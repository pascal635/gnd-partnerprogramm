<?php

namespace App\Filament\Resources\Conversions;

use App\Filament\Resources\Conversions\Pages\ListConversions;
use App\Filament\Resources\Conversions\Tables\ConversionsTable;
use App\Models\Conversion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConversionResource extends Resource
{
    protected static ?string $model = Conversion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Partnerverwaltung';

    protected static ?string $navigationLabel = 'Aufträge';

    protected static ?string $modelLabel = 'Auftrag';

    protected static ?string $pluralModelLabel = 'Aufträge';

    protected static ?int $navigationSort = 3;

    // Orders arrive via webhook; manual entry uses its own header action.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['lead.partner', 'commission', 'customer']);
    }

    public static function table(Table $table): Table
    {
        return ConversionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversions::route('/'),
        ];
    }
}
