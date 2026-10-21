<?php

namespace App\Filament\Resources\Conversions\Pages;

use App\Filament\Resources\Conversions\ConversionResource;
use App\Filament\Resources\Conversions\Schemas\AttributionFields;
use App\Models\Conversion;
use App\Services\Attribution\ManualAttribution;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;

class ListConversions extends ListRecords
{
    protected static string $resource = ConversionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manualCreate')
                ->label('Auftrag manuell erfassen')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modalDescription('Für beauftragte Gutachten, die nicht über das System kamen. Die Provision wird sofort berechnet.')
                ->schema([
                    Grid::make(2)->schema([
                        ...AttributionFields::make(),
                        TextInput::make('customer_name')->label('Kunde (Name)')->maxLength(191),
                        TextInput::make('customer_email')->label('Kunde (E-Mail)')->email()->maxLength(191)
                            ->helperText('Damit werden auch spätere Aufträge des Kunden erkannt.'),
                        TextInput::make('deal_value')->label('Auftragswert (netto, €)')->numeric()->minValue(0),
                        DatePicker::make('converted_at')->label('Beauftragt am')->default(now())->required()->native(false),
                        TextInput::make('deal_id')->label('Pipedrive Deal-ID (optional)')->maxLength(191)
                            ->unique(table: Conversion::class, column: 'external_deal_id')
                            ->helperText('Ein späterer Zapier-Webhook zu diesem Deal ändert die Zuordnung nicht.')
                            ->columnSpanFull(),
                    ]),
                ])
                ->action(function (array $data): void {
                    app(ManualAttribution::class)->createManual($data);
                    Notification::make()->title('Auftrag erfasst')->body('Provision wurde berechnet.')->success()->send();
                }),
        ];
    }
}
