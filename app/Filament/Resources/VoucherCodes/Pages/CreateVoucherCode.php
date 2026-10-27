<?php

namespace App\Filament\Resources\VoucherCodes\Pages;

use App\Enums\SyncStatus;
use App\Filament\Resources\VoucherCodes\Schemas\VoucherCodeForm;
use App\Filament\Resources\VoucherCodes\VoucherCodeResource;
use App\Services\Attribution\LateCodeAttribution;
use App\Services\Voucher\VoucherProvisioning;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

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
        // WordPress sync + welcome mail; failures keep the code and only warn.
        foreach (app(VoucherProvisioning::class)->afterCreate($this->record) as $warning) {
            Notification::make()->title($warning['title'])->body($warning['body'])->warning()->send();
        }

        LateCodeAttribution::notifyBackfilled(app(LateCodeAttribution::class)->backfill($this->record));
    }
}
