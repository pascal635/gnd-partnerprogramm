<?php

namespace App\Filament\Portal\Resources\Leads;

use App\Filament\Portal\Resources\Leads\Pages\ListLeads;
use App\Filament\Portal\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Empfehlungen';

    protected static ?string $modelLabel = 'Empfehlung';

    protected static ?string $pluralModelLabel = 'Empfehlungen';

    // Partners only ever read; never create/edit leads.
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * TENANT ISOLATION: a partner sees only their own referrals. This is the
     * DSGVO boundary — enforced server-side on every query for this resource.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('partner_id', auth()->user()?->partner_id)
            ->with('conversion.commission');
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
        ];
    }
}
