<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->id !== auth()->id()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Aktuelle Rolle ins virtuelle Formularfeld laden.
        $data['role'] = $this->record->roles->first()?->name ?? 'employee';

        return $data;
    }

    protected function afterSave(): void
    {
        $role = $this->data['role'] ?? 'employee';
        $this->record->syncRoles([$role]);
    }
}
