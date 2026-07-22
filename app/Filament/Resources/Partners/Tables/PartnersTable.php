<?php

namespace App\Filament\Resources\Partners\Tables;

use App\Enums\PartnerStatus;
use App\Enums\PartnerType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PartnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('Firma')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('partner_type')
                    ->label('Typ')
                    ->badge(),
                TextColumn::make('contact_person')
                    ->label('Ansprechpartner')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('voucher_codes_count')
                    ->label('Codes')
                    ->counts('voucherCodes')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('leads_count')
                    ->label('Ersteinschätzungen')
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('converted_leads_count')
                    ->label('Beauftragt')
                    ->badge()
                    ->color('success')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('conversion_rate')
                    ->label('Conversion')
                    ->alignCenter()
                    ->state(fn ($record): string => $record->leads_count > 0
                        ? round($record->converted_leads_count / $record->leads_count * 100).' %'
                        : '—'),
                TextColumn::make('provision_total')
                    ->label('Provision')
                    ->state(fn ($record): float => (float) ($record->provision_total ?? 0))
                    ->money('EUR', locale: 'de')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('city')
                    ->label('Ort')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('company_name')
            ->filters([
                SelectFilter::make('partner_type')->label('Typ')->options(PartnerType::class),
                SelectFilter::make('status')->label('Status')->options(PartnerStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
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
