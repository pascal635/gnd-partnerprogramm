<?php

namespace App\Filament\Resources\VoucherCodes\Pages;

use App\Enums\SyncStatus;
use App\Filament\Resources\VoucherCodes\Schemas\VoucherCodeForm;
use App\Filament\Resources\VoucherCodes\VoucherCodeResource;
use App\Jobs\SyncVoucherToWordPress;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditVoucherCode extends EditRecord
{
    protected static string $resource = VoucherCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = VoucherCodeForm::fillCommissionRaw($data);
        $data['sync_status'] = SyncStatus::Pending->value;

        return $data;
    }

    protected function afterSave(): void
    {
        // Direkt (synchron) an WordPress – kein Cron nötig.
        try {
            SyncVoucherToWordPress::dispatchSync($this->record);
        } catch (Throwable $e) {
            $this->record->update(['sync_status' => SyncStatus::Failed->value]);
            Notification::make()
                ->title('WordPress-Sync fehlgeschlagen')
                ->body('Änderung gespeichert, aber nicht an WordPress übertragen. Bitte „Erneut senden".')
                ->warning()
                ->send();
        }
    }
}
