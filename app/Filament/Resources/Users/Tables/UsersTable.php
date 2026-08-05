<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('roles.name')
                    ->label('Rolle')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Administrator',
                        'employee' => 'Mitarbeiter',
                        default => $state,
                    }),
                IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label('Letzter Login')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                // Kein Löschen des eigenen Kontos (Selbst-Aussperren verhindern).
                DeleteAction::make()
                    ->visible(fn (User $record): bool => $record->id !== auth()->id()),
            ]);
    }
}
