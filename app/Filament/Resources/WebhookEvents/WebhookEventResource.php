<?php

namespace App\Filament\Resources\WebhookEvents;

use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Filament\Resources\WebhookEvents\Tables\WebhookEventsTable;
use App\Models\WebhookEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebhookEventResource extends Resource
{
    protected static ?string $model = WebhookEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|\UnitEnum|null $navigationGroup = 'Integrationen';

    protected static ?string $navigationLabel = 'Webhook-Protokoll';

    protected static ?string $modelLabel = 'Webhook-Event';

    protected static ?string $pluralModelLabel = 'Webhook-Protokoll';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return WebhookEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookEvents::route('/'),
        ];
    }
}
