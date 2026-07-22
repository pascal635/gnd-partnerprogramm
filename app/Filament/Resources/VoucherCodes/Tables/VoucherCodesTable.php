<?php

namespace App\Filament\Resources\VoucherCodes\Tables;

use App\Enums\SyncStatus;
use App\Jobs\SyncVoucherToWordPress;
use App\Models\VoucherCode;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class VoucherCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('partner.company_name')
                    ->label('Partner')
                    ->placeholder('— Promo —')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Rabatt-Typ')
                    ->badge(),
                TextColumn::make('value')
                    ->label('Wert')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('commission_raw')
                    ->label('Provision')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean(),
                TextColumn::make('sync_status')
                    ->label('WP-Sync')
                    ->badge(),
                TextColumn::make('synced_to_wp_at')
                    ->label('Zuletzt sync.')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('sync_status')
                    ->label('WP-Sync')
                    ->options(SyncStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('resync')
                    ->label('Erneut senden')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Den Gutscheincode erneut an WordPress senden?')
                    ->action(function (VoucherCode $record) {
                        $record->update(['sync_status' => SyncStatus::Pending]);
                        SyncVoucherToWordPress::dispatch($record);
                        Notification::make()
                            ->title('Synchronisierung gestartet')
                            ->body("Code {$record->code} wird erneut an WordPress gesendet.")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
