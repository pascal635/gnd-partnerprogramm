<?php

namespace App\Filament\Resources\Conversions\Tables;

use App\Enums\CommissionCalcStatus;
use App\Enums\ConversionStatus;
use App\Filament\Resources\Conversions\Schemas\AttributionFields;
use App\Models\Conversion;
use App\Models\Partner;
use App\Models\VoucherCode;
use App\Services\Attribution\LateCodeAttribution;
use App\Services\Attribution\ManualAttribution;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class ConversionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('converted_at')
                    ->label('Beauftragt am')
                    ->dateTime('d.m.Y')
                    ->sortable(),
                TextColumn::make('lead.external_lead_id')
                    ->label('Lead-Ref')
                    ->state(fn (Conversion $record): ?string => $record->lead?->referenceId())
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('lead.customer_name')
                    ->label('Kunde')
                    ->description(fn (Conversion $record): ?string => $record->lead?->customer_email)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('lead.partner.company_name')
                    ->label('Partner')
                    ->weight('bold')
                    ->placeholder('nicht zugeordnet'),
                TextColumn::make('lead.voucher_code_raw')
                    ->label('Eingegebener Code')
                    ->description(function (Conversion $record): ?string {
                        $pipedrive = LateCodeAttribution::normalize(data_get($record->raw_payload, 'voucher_code'));

                        return $pipedrive && $pipedrive !== LateCodeAttribution::normalize($record->lead?->voucher_code_raw)
                            ? "Pipedrive: {$pipedrive}"
                            : null;
                    })
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('lead.attributed_via')
                    ->label('Zuordnung')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('deal_value')
                    ->label('Auftragswert')
                    ->money('EUR', locale: 'de')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('commission.amount')
                    ->label('Provision')
                    ->money('EUR', locale: 'de')
                    ->placeholder('—'),
                TextColumn::make('commission.status')
                    ->label('Provisionsstatus')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('external_deal_id')
                    ->label('Deal-ID')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('converted_at', 'desc')
            ->filters([
                Filter::make('unassigned')
                    ->label('Nur nicht zugeordnete')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q
                        ->where('status', ConversionStatus::NeedsReview->value)
                        ->orWhereDoesntHave('lead', fn (Builder $l) => $l->whereNotNull('partner_id')))),
                SelectFilter::make('partner')
                    ->label('Partner')
                    ->options(fn (): array => Partner::query()->orderBy('company_name')->pluck('company_name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $partnerId) => $q->whereHas('lead', fn (Builder $l) => $l->where('partner_id', $partnerId)),
                    )),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ConversionStatus::class),
                Filter::make('converted_at')
                    ->schema([
                        DatePicker::make('from')->label('Von')->native(false),
                        DatePicker::make('until')->label('Bis')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('converted_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('converted_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('assign')
                    ->label('Partner zuordnen')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('warning')
                    ->disabled(fn (Conversion $record): bool => app(ManualAttribution::class)->isLocked($record))
                    ->tooltip(fn (Conversion $record): ?string => app(ManualAttribution::class)->isLocked($record)
                        ? 'Provision ist bereits freigegeben oder ausbezahlt.'
                        : null)
                    ->modalDescription('Ordnet diesen Auftrag nachträglich einem Partner zu und berechnet die Provision neu.')
                    ->fillForm(fn (Conversion $record): array => [
                        'partner_id' => $record->lead?->partner_id,
                        'voucher_code_id' => $record->lead?->voucher_code_id,
                        'reassign_customer' => true,
                    ])
                    ->schema([
                        ...AttributionFields::make(),
                        Toggle::make('reassign_customer')
                            ->label('Kunde dauerhaft diesem Partner zuordnen')
                            ->helperText('Künftige Aufträge dieses Kunden gehen dann ebenfalls an diesen Partner.')
                            ->visible(fn ($record): bool => $record?->customer_id !== null),
                    ])
                    ->action(function (Conversion $record, array $data): void {
                        try {
                            app(ManualAttribution::class)->assign(
                                $record,
                                Partner::findOrFail($data['partner_id']),
                                filled($data['voucher_code_id'] ?? null) ? VoucherCode::find($data['voucher_code_id']) : null,
                                (bool) ($data['reassign_customer'] ?? false),
                            );
                            Notification::make()->title('Auftrag zugeordnet')->body('Provision wurde neu berechnet.')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Nicht möglich')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('dealValue')
                    ->label('Auftragswert nachtragen')
                    ->icon(Heroicon::OutlinedCurrencyEuro)
                    ->color('gray')
                    ->visible(fn (Conversion $record): bool => $record->deal_value === null
                        || $record->commission?->calc_status === CommissionCalcStatus::PendingInput)
                    ->disabled(fn (Conversion $record): bool => app(ManualAttribution::class)->isLocked($record))
                    ->schema([
                        TextInput::make('deal_value')
                            ->label('Auftragswert (netto, €)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->action(function (Conversion $record, array $data): void {
                        try {
                            app(ManualAttribution::class)->setDealValue($record, (float) $data['deal_value']);
                            Notification::make()->title('Auftragswert gespeichert')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Nicht möglich')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
