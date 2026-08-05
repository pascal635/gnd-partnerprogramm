<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Team-/Admin-Logins haben nie eine partner_id.
        $data['partner_id'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        // 'role' ist ein virtuelles Formularfeld (dehydrated(false)).
        $role = $this->data['role'] ?? 'employee';
        $this->record->syncRoles([$role]);
    }
}
