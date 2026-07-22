<?php

namespace App\Filament\Resources\Commissions\Tables;

use App\Enums\CommissionStatus;
use App\Models\Commission;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('partner.company_name')
                    ->label('Partner')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('lead.external_lead_id')
                    ->label('Referenz')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR', locale: 'de')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('commission_kind')
                    ->label('Art')
                    ->badge(),
                TextColumn::make('calc_status')
                    ->label('Berechnung')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Auszahlung')
                    ->badge()
                    ->sortable(),
                TextColumn::make('conversion.converted_at')
                    ->label('Beauftragt am')
                    ->dateTime('d.m.Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Ausbezahlt am')
                    ->dateTime('d.m.Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Auszahlungsstatus')
                    ->options(CommissionStatus::class),
                SelectFilter::make('partner')
                    ->label('Partner')
                    ->relationship('partner', 'company_name')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('Von')->native(false),
                        DatePicker::make('until')->label('Bis')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Freigeben')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Commission $record): bool => $record->status === CommissionStatus::Pending)
                    ->action(function (Commission $record): void {
                        $record->update([
                            'status' => CommissionStatus::Approved,
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);
                        Notification::make()->title('Provision freigegeben')->success()->send();
                    }),
                Action::make('markPaid')
                    ->label('Als ausbezahlt markieren')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (Commission $record): bool => in_array($record->status, [CommissionStatus::Pending, CommissionStatus::Approved], true))
                    ->schema([
                        TextInput::make('payout_reference')
                            ->label('Auszahlungs-Referenz')
                            ->maxLength(191)
                            ->placeholder('z. B. Überweisung Juli 2026'),
                        DatePicker::make('paid_at')
                            ->label('Auszahlungsdatum')
                            ->default(now())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (Commission $record, array $data): void {
                        $record->update([
                            'status' => CommissionStatus::Paid,
                            'approved_by' => $record->approved_by ?? auth()->id(),
                            'approved_at' => $record->approved_at ?? now(),
                            'paid_at' => $data['paid_at'],
                            'payout_reference' => $data['payout_reference'] ?? null,
                        ]);
                        Notification::make()->title('Als ausbezahlt markiert')->success()->send();
                    }),
                Action::make('cancel')
                    ->label('Stornieren')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Commission $record): bool => $record->status !== CommissionStatus::Cancelled)
                    ->action(function (Commission $record): void {
                        $record->update(['status' => CommissionStatus::Cancelled]);
                        Notification::make()->title('Provision storniert')->warning()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveBulk')
                        ->label('Freigeben')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $n = 0;
                            foreach ($records as $record) {
                                if ($record->status === CommissionStatus::Pending) {
                                    $record->update([
                                        'status' => CommissionStatus::Approved,
                                        'approved_by' => auth()->id(),
                                        'approved_at' => now(),
                                    ]);
                                    $n++;
                                }
                            }
                            Notification::make()->title("{$n} Provision(en) freigegeben")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markPaidBulk')
                        ->label('Als ausbezahlt markieren')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->color('success')
                        ->schema([
                            TextInput::make('payout_reference')
                                ->label('Auszahlungs-Referenz')
                                ->maxLength(191),
                            DatePicker::make('paid_at')
                                ->label('Auszahlungsdatum')
                                ->default(now())
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $n = 0;
                            foreach ($records as $record) {
                                if (in_array($record->status, [CommissionStatus::Pending, CommissionStatus::Approved], true)) {
                                    $record->update([
                                        'status' => CommissionStatus::Paid,
                                        'approved_by' => $record->approved_by ?? auth()->id(),
                                        'approved_at' => $record->approved_at ?? now(),
                                        'paid_at' => $data['paid_at'],
                                        'payout_reference' => $data['payout_reference'] ?? null,
                                    ]);
                                    $n++;
                                }
                            }
                            Notification::make()->title("{$n} Provision(en) als ausbezahlt markiert")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
