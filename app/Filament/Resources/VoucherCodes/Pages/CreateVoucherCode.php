<?php

namespace App\Filament\Resources\VoucherCodes\Pages;

use App\Enums\SyncStatus;
use App\Filament\Resources\VoucherCodes\Schemas\VoucherCodeForm;
use App\Filament\Resources\VoucherCodes\VoucherCodeResource;
use App\Jobs\SyncVoucherToWordPress;
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
        SyncVoucherToWordPress::dispatch($this->record);
    }
}
