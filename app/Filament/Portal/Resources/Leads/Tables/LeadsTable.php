<?php

namespace App\Filament\Portal\Resources\Leads\Tables;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Pseudonymous reference — NEVER the customer's name/contact.
                TextColumn::make('external_lead_id')
                    ->label('Referenz-Nr.')
                    ->searchable()
                    ->weight('bold')
                    ->getStateUsing(fn (Lead $record): string => $record->referenceId()),
                TextColumn::make('submitted_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y')
                    ->sortable(),
                TextColumn::make('ort')
                    ->label('Ort')
                    ->placeholder('—'),
                TextColumn::make('property_type')
                    ->label('Objektart')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('provision')
                    ->label('Provision')
                    ->state(fn (Lead $record) => $record->conversion?->commission?->amount)
                    ->money('EUR', locale: 'de')
                    ->placeholder('—'),
                TextColumn::make('provision_status')
                    ->label('Provisionsstatus')
                    ->badge()
                    ->state(fn (Lead $record) => $record->conversion?->commission?->status)
                    ->placeholder('—'),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(LeadStatus::class),
            ]);
    }
}
