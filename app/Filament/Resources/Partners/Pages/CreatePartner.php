<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use App\Support\PartnerInvitation;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Throwable;

class CreatePartner extends CreateRecord
{
    protected static string $resource = PartnerResource::class;

    protected function afterCreate(): void
    {
        // The "Partner-Login anlegen" toggle is non-dehydrated; read raw state.
        if (! ($this->data['create_login'] ?? false) || blank($this->record->email)) {
            return;
        }

        try {
            PartnerInvitation::send($this->record);

            Notification::make()
                ->title('Partner-Login angelegt')
                ->body("Einladung mit Passwort-Link an {$this->record->email} gesendet.")
                ->success()
                ->send();
        } catch (Throwable $e) {
            // Login besteht trotzdem – nur der Mailversand hat gehakt.
            Notification::make()
                ->title('Partner-Login angelegt')
                ->body('Konto erstellt, aber die Einladungs-Mail konnte nicht gesendet werden. Bitte E-Mail-Einstellungen prüfen und Einladung erneut senden.')
                ->warning()
                ->send();
        }
    }
}
