<?php

namespace App\Filament\Resources\VoucherCodes;

use App\Filament\Resources\VoucherCodes\Pages\CreateVoucherCode;
use App\Filament\Resources\VoucherCodes\Pages\EditVoucherCode;
use App\Filament\Resources\VoucherCodes\Pages\ListVoucherCodes;
use App\Filament\Resources\VoucherCodes\Schemas\VoucherCodeForm;
use App\Filament\Resources\VoucherCodes\Tables\VoucherCodesTable;
use App\Models\VoucherCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VoucherCodeResource extends Resource
{
    protected static ?string $model = VoucherCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|\UnitEnum|null $navigationGroup = 'Partnerverwaltung';

    protected static ?string $navigationLabel = 'Gutscheincodes';

    protected static ?string $modelLabel = 'Gutscheincode';

    protected static ?string $pluralModelLabel = 'Gutscheincodes';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return VoucherCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VoucherCodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVoucherCodes::route('/'),
            'create' => CreateVoucherCode::route('/create'),
            'edit' => EditVoucherCode::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
