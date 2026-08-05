<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Benutzer')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(191),
                        TextInput::make('email')
                            ->label('E-Mail (Login)')
                            ->email()
                            ->required()
                            ->maxLength(191)
                            ->unique(ignoreRecord: true),
                        Select::make('role')
                            ->label('Rolle')
                            ->options([
                                'admin' => 'Administrator',
                                'employee' => 'Mitarbeiter',
                            ])
                            ->default('employee')
                            ->native(false)
                            ->required()
                            // Rolle wird per spatie verwaltet, nicht als Spalte gespeichert.
                            ->dehydrated(false)
                            ->helperText('Administratoren dürfen u. a. Benutzer verwalten.'),
                        Toggle::make('is_active')
                            ->label('Aktiv')
                            ->default(true),
                        TextInput::make('password')
                            ->label('Passwort')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
                            ->helperText('Beim Bearbeiten leer lassen, um das Passwort beizubehalten.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
