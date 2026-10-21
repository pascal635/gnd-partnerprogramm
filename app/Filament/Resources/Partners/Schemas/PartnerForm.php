<?php

namespace App\Filament\Resources\Partners\Schemas;

use App\Enums\PartnerStatus;
use App\Enums\PartnerType;
use App\Models\Partner;
use App\Rules\CommissionRate;
use App\Services\Commission\CommissionCalculator;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Unternehmen')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Firmenname')
                            ->required()
                            ->maxLength(191),
                        Select::make('partner_type')
                            ->label('Partner-Typ')
                            ->options(PartnerType::class)
                            ->default(PartnerType::Sonstige->value)
                            ->required(),
                        TextInput::make('vat_id')
                            ->label('USt-IdNr. / Steuernummer')
                            ->maxLength(32),
                        Select::make('status')
                            ->label('Status')
                            ->options(PartnerStatus::class)
                            ->default(PartnerStatus::Active->value)
                            ->required(),
                    ]),

                Section::make('Geworben von')
                    ->description('Wurde dieser Partner von einem anderen Partner geworben, erhält der Werber zusätzlich eine Provision auf die Aufträge dieses Partners.')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        Select::make('recruited_by_partner_id')
                            ->label('Geworben von (Werber)')
                            ->options(fn (?Partner $record): array => Partner::query()
                                ->when($record, fn (Builder $q) => $q
                                    ->whereKeyNot($record->getKey())
                                    ->where(fn (Builder $w) => $w
                                        ->whereNull('recruited_by_partner_id')
                                        ->orWhere('recruited_by_partner_id', '!=', $record->getKey())))
                                ->orderBy('company_name')
                                ->pluck('company_name', 'id')
                                ->all())
                            ->searchable()
                            ->placeholder('— niemand —'),
                        TextInput::make('recruiter_commission_raw')
                            ->label('Werber-Provision')
                            ->placeholder(fn (): string => 'Standard: '.CommissionCalculator::recruiterDefault())
                            ->maxLength(50)
                            ->rule(new CommissionRate)
                            ->helperText('Prozent vom Auftragswert (z. B. „5%") oder Fixbetrag je Auftrag (z. B. „50"). Leer = Standard.'),
                    ]),

                Section::make('Ansprechpartner & Login')
                    ->columns(2)
                    ->schema([
                        Select::make('salutation')
                            ->label('Anrede')
                            ->options([
                                'Herr' => 'Herr',
                                'Frau' => 'Frau',
                                'Divers' => 'Divers',
                            ])
                            ->native(false),
                        TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(64),
                        TextInput::make('first_name')
                            ->label('Vorname')
                            ->maxLength(191),
                        TextInput::make('last_name')
                            ->label('Nachname')
                            ->maxLength(191),
                        TextInput::make('email')
                            ->label('E-Mail (Login)')
                            ->email()
                            ->required()
                            ->maxLength(191)
                            ->helperText('Dient als Login für das Partner-Portal.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Adresse')
                    ->columns(3)
                    ->schema([
                        TextInput::make('street')->label('Straße & Nr.')->columnSpan(3),
                        TextInput::make('zip')->label('PLZ')->maxLength(16),
                        TextInput::make('city')->label('Ort')->columnSpan(2),
                        TextInput::make('country')->label('Land')->default('DE')->maxLength(2),
                    ]),

                Section::make('Bankverbindung')
                    ->description('Für die Auszahlung der Provision.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('account_holder')
                            ->label('Kontoinhaber')
                            ->maxLength(191),
                        TextInput::make('bic')
                            ->label('BIC')
                            ->maxLength(32),
                        TextInput::make('iban_encrypted')
                            ->label('IBAN')
                            ->maxLength(40)
                            ->helperText('Verschlüsselt gespeichert.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Fachartikel')
                    ->description('Link zum vom Partner veröffentlichten Fachartikel.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('article_url')
                            ->label('Artikel-Link')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        DateTimePicker::make('article_verified_at')
                            ->label('Geprüft am')
                            ->native(false)
                            ->helperText('Setzen, wenn die Veröffentlichung geprüft wurde.'),
                    ]),

                Section::make('Portal-Zugang')
                    ->visibleOn('create')
                    ->schema([
                        Toggle::make('create_login')
                            ->label('Partner-Login anlegen')
                            ->helperText('Legt ein Konto für das Partner-Portal an und sendet eine Einladung.')
                            ->default(true)
                            ->dehydrated(false),
                    ]),

                Section::make('Intern')
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')->label('Notizen')->columnSpanFull(),
                    ]),
            ]);
    }
}
