<?php

namespace App\Filament\Resources\Partners\RelationManagers;

use App\Enums\CommissionKind;
use App\Enums\CommissionStatus;
use App\Enums\LeadStatus;
use App\Enums\PartnerType;
use App\Enums\VoucherType;
use App\Models\Partner;
use App\Models\User;
use App\Models\VoucherCode;
use App\Rules\CommissionRate;
use App\Services\Commission\CommissionCalculator;
use App\Services\Partner\RecruitOnboarding;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** "Subpartner": partners recruited by this partner, who earns a recruiter provision on their orders. */
class RecruitsRelationManager extends RelationManager
{
    protected static string $relationship = 'recruits';

    protected static ?string $title = 'Subpartner';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('company_name')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['leads as orders_count' => fn (Builder $q) => $q->where('status', LeadStatus::Converted->value)])
                ->withSum(['sourcedCommissions as recruiter_total' => fn (Builder $q) => $q
                    ->where('status', '!=', CommissionStatus::Cancelled->value)], 'amount'))
            ->heading('Subpartner')
            ->description('Für jeden Auftrag eines Subpartners erhält dieser Partner zusätzlich die Werber-Provision.')
            ->columns([
                TextColumn::make('company_name')
                    ->label('Subpartner')
                    ->weight('bold')
                    ->description(fn (Partner $record): string => $record->displayName())
                    ->searchable(),
                TextColumn::make('voucherCodes.code')
                    ->label('Code(s)')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('recruiter_commission_raw')
                    ->label('Werber-Provision')
                    ->state(fn (Partner $record): string => $record->recruiter_commission_raw
                        ?: 'Standard ('.CommissionCalculator::recruiterDefault().')'),
                TextColumn::make('orders_count')
                    ->label('Aufträge')
                    ->alignCenter(),
                TextColumn::make('recruiter_total')
                    ->label('Werber-Provision gesamt')
                    ->state(fn (Partner $record): float => (float) ($record->recruiter_total ?? 0))
                    ->money('EUR', locale: 'de'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->headerActions([
                $this->createRecruitAction(),
                $this->attachRecruitAction(),
            ])
            ->recordActions([
                Action::make('editRate')
                    ->label('Werber-Provision ändern')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->fillForm(fn (Partner $record): array => ['recruiter_commission_raw' => $record->recruiter_commission_raw])
                    ->schema([
                        $this->rateField(),
                        Toggle::make('recalculate')
                            ->label('Offene Aufträge mit neuem Satz nachberechnen'),
                    ])
                    ->action(function (Partner $record, array $data): void {
                        $record->update(['recruiter_commission_raw' => filled($data['recruiter_commission_raw'] ?? null)
                            ? trim($data['recruiter_commission_raw']) : null]);
                        $n = ! empty($data['recalculate']) ? app(RecruitOnboarding::class)->recalculate($record) : 0;
                        Notification::make()->title('Werber-Provision gespeichert')
                            ->body($n ? "{$n} Auftrag/Aufträge nachberechnet." : null)->success()->send();
                    }),
                Action::make('detach')
                    ->label('Zuordnung lösen')
                    ->icon(Heroicon::OutlinedLinkSlash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Der Partner ist danach kein Subpartner mehr. Noch nicht freigegebene Werber-Provisionen werden entfernt; freigegebene und ausbezahlte bleiben bestehen.')
                    ->action(function (Partner $record): void {
                        app(RecruitOnboarding::class)->detach($record);
                        Notification::make()->title('Zuordnung gelöst')->success()->send();
                    }),
            ]);
    }

    private function createRecruitAction(): Action
    {
        return Action::make('createRecruit')
            ->label('Subpartner anlegen')
            ->icon(Heroicon::OutlinedUserPlus)
            ->modalWidth('3xl')
            ->modalDescription('Der Subpartner ist ein vollwertiger Partner mit eigenem Login, eigenem Gutscheincode und eigenen Konditionen.')
            ->schema([
                Section::make('Subpartner')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')->label('Firmenname')->required()->maxLength(191),
                        Select::make('partner_type')->label('Partner-Typ')->options(PartnerType::class)
                            ->default(PartnerType::Sonstige->value)->required(),
                        Select::make('salutation')->label('Anrede')->native(false)
                            ->options(['Herr' => 'Herr', 'Frau' => 'Frau', 'Divers' => 'Divers']),
                        TextInput::make('phone')->label('Telefon')->tel()->maxLength(64),
                        TextInput::make('first_name')->label('Vorname')->maxLength(191),
                        TextInput::make('last_name')->label('Nachname')->maxLength(191),
                        TextInput::make('email')->label('E-Mail (Login)')->email()->required()->maxLength(191)
                            ->unique(table: User::class, column: 'email')
                            ->columnSpanFull(),
                    ]),
                Section::make('Werber-Provision für '.$this->getOwnerRecord()->company_name)
                    ->schema([$this->rateField()]),
                Section::make('Gutscheincode des Subpartners')
                    ->schema([
                        Toggle::make('create_voucher')->label('Gutscheincode direkt anlegen')->default(true)->live(),
                        Grid::make(2)
                            ->visible(fn (Get $get): bool => (bool) $get('create_voucher'))
                            ->schema([
                                TextInput::make('code')->label('Code')->maxLength(64)
                                    ->required(fn (Get $get): bool => (bool) $get('create_voucher'))
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->rule('regex:/^[A-Za-z0-9_-]+$/')
                                    ->unique(table: VoucherCode::class, column: 'code')
                                    ->helperText('Nur Buchstaben, Ziffern, „-" und „_".'),
                                Select::make('type')->label('Rabatt-Typ')->options(VoucherType::class)
                                    ->default(VoucherType::Prozent->value)
                                    ->required(fn (Get $get): bool => (bool) $get('create_voucher')),
                                TextInput::make('value')->label('Rabatt-Wert')->numeric()->minValue(0)
                                    ->required(fn (Get $get): bool => (bool) $get('create_voucher')),
                                Select::make('commission_kind')->label('Provision des Subpartners')
                                    ->options(CommissionKind::class)
                                    ->default(CommissionKind::Percent->value)
                                    ->selectablePlaceholder(false)
                                    ->live(),
                                TextInput::make('commission_value')->label('Provisions-Wert')->numeric()->minValue(0)
                                    ->visible(fn (Get $get): bool => $this->kind($get('commission_kind')) !== CommissionKind::None->value)
                                    ->suffix(fn (Get $get): string => $this->kind($get('commission_kind')) === CommissionKind::Percent->value ? '%' : '€'),
                            ]),
                    ]),
                Toggle::make('invite')->label('Einladung ins Partnerportal senden')->default(true),
            ])
            ->action(function (array $data): void {
                $result = app(RecruitOnboarding::class)->create($this->getOwnerRecord(), $data);

                Notification::make()->title('Subpartner angelegt')
                    ->body($result['partner']->company_name.' wurde als Subpartner angelegt.')->success()->send();

                foreach ($result['warnings'] as $warning) {
                    Notification::make()->title($warning['title'])->body($warning['body'])->warning()->send();
                }
            });
    }

    private function attachRecruitAction(): Action
    {
        return Action::make('attachRecruit')
            ->label('Bestehenden Partner zuordnen')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->schema([
                Select::make('recruit_id')
                    ->label('Partner')
                    ->options(function (): array {
                        $owner = $this->getOwnerRecord();

                        return Partner::query()
                            ->whereKeyNot($owner->getKey())
                            ->whereNull('recruited_by_partner_id')
                            ->when($owner->recruited_by_partner_id, fn (Builder $q, $id) => $q->whereKeyNot($id))
                            ->orderBy('company_name')
                            ->pluck('company_name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->required(),
                $this->rateField(),
                Toggle::make('recalculate')
                    ->label('Bestehende offene Aufträge nachberechnen')
                    ->helperText('Erzeugt die Werber-Provision auch für vorhandene, noch nicht freigegebene Aufträge dieses Partners.'),
            ])
            ->action(function (array $data): void {
                $n = app(RecruitOnboarding::class)->attach(
                    $this->getOwnerRecord(),
                    Partner::findOrFail($data['recruit_id']),
                    $data['recruiter_commission_raw'] ?? null,
                    ! empty($data['recalculate']),
                );

                Notification::make()->title('Subpartner zugeordnet')
                    ->body($n ? "{$n} Auftrag/Aufträge nachberechnet." : null)->success()->send();
            });
    }

    private function rateField(): TextInput
    {
        return TextInput::make('recruiter_commission_raw')
            ->label('Werber-Provision')
            ->placeholder('Standard: '.CommissionCalculator::recruiterDefault())
            ->maxLength(50)
            ->rule(new CommissionRate)
            ->helperText('Prozent vom Auftragswert (z. B. „5%") oder Fixbetrag je Auftrag (z. B. „50"). Leer = Standard.');
    }

    private function kind(mixed $state): string
    {
        return $state instanceof \BackedEnum ? $state->value : (string) $state;
    }
}
