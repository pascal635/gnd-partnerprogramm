<?php

namespace App\Filament\Resources\VoucherCodes\Pages;

use App\Enums\SyncStatus;
use App\Filament\Resources\VoucherCodes\Schemas\VoucherCodeForm;
use App\Filament\Resources\VoucherCodes\VoucherCodeResource;
use App\Jobs\SyncVoucherToWordPress;
use App\Mail\PartnerWelcomeMail;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CreateVoucherCode extends CreateRecord
{
    protected static string $resource = VoucherCodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = VoucherCodeForm::fillCommissionRaw($data);
        $data['created_by'] = auth()->id();
        $data['sync_status'] = SyncStatus::Pending->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Direkt (synchron) an WordPress senden – kein Cron nötig. Ein Fehler
        // bricht das Speichern nicht ab; der Code bleibt als "Sync
        // fehlgeschlagen" und kann per "Erneut senden" nachgereicht werden.
        try {
            SyncVoucherToWordPress::dispatchSync($this->record);
        } catch (Throwable $e) {
            $this->record->update(['sync_status' => SyncStatus::Failed->value]);
            Notification::make()
                ->title('WordPress-Sync fehlgeschlagen')
                ->body('Der Code wurde gespeichert, aber nicht an WordPress übertragen. Bitte später „Erneut senden".')
                ->warning()
                ->send();
        }

        // Willkommens-Mail sofort an den Partner (nur bei zugeordnetem Partner
        // mit E-Mail; Promo-Codes ohne Partner erhalten keine).
        $partner = $this->record->partner;

        if ($partner && filled($partner->email)) {
            try {
                Mail::to($partner->email)->send(new PartnerWelcomeMail($this->record));
            } catch (Throwable $e) {
                Notification::make()
                    ->title('Willkommens-Mail konnte nicht gesendet werden')
                    ->body('Der Code wurde gespeichert. Bitte die E-Mail-Einstellungen prüfen.')
                    ->warning()
                    ->send();
            }
        }
    }
}
