<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreatePartner extends CreateRecord
{
    protected static string $resource = PartnerResource::class;

    protected function afterCreate(): void
    {
        // The "Partner-Login anlegen" toggle is non-dehydrated; read raw state.
        if (! ($this->data['create_login'] ?? false) || blank($this->record->email)) {
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $this->record->email],
            [
                'name' => $this->record->company_name,
                'partner_id' => $this->record->id,
                'password' => Hash::make(Str::random(40)),
                'is_active' => true,
            ],
        );

        // Ensure link + role even if the user row already existed.
        $user->forceFill(['partner_id' => $this->record->id])->save();
        $user->syncRoles('partner');

        // TODO(task #7): send a magic-link invitation email here.
        Notification::make()
            ->title('Partner-Login angelegt')
            ->body("Konto für {$this->record->email} erstellt. Einladung per Magic-Link folgt.")
            ->success()
            ->send();
    }
}
