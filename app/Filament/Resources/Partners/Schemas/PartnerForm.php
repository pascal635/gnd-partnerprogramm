<?php

namespace App\Filament\Resources\Partners\Schemas;

use App\Enums\PartnerStatus;
use App\Enums\PartnerType;
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

                Section::make('Ansprechpartner & Login')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_person')
                            ->label('Ansprechpartner')
                            ->maxLength(191),
                        TextInput::make('email')
                            ->label('E-Mail (Login)')
                            ->email()
                            ->required()
                            ->maxLength(191)
                            ->helperText('Dient als Login für das Partner-Portal.'),
                        TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(64),
                    ]),

                Section::make('Adresse')
                    ->columns(3)
                    ->schema([
                        TextInput::make('street')->label('Straße & Nr.')->columnSpan(3),
                        TextInput::make('zip')->label('PLZ')->maxLength(16),
                        TextInput::make('city')->label('Ort')->columnSpan(2),
                        TextInput::make('country')->label('Land')->default('DE')->maxLength(2),
                    ]),

                Section::make('Portal-Zugang')
                    ->visibleOn('create')
                    ->schema([
                        Toggle::make('create_login')
                            ->label('Partner-Login anlegen')
                            ->helperText('Legt ein Konto für das Partner-Portal an (Einladung per Magic-Link folgt).')
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
