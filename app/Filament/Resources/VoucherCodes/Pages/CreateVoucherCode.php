<?php

namespace App\Filament\Resources\VoucherCodes\Pages;

use App\Enums\SyncStatus;
use App\Filament\Resources\VoucherCodes\Schemas\VoucherCodeForm;
use App\Filament\Resources\VoucherCodes\VoucherCodeResource;
use App\Jobs\SyncVoucherToWordPress;
use App\Mail\PartnerWelcomeMail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;

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

        // Willkommens-Mail an den Partner (nur bei zugeordnetem Partner mit
        // E-Mail; Promo-Codes ohne Partner erhalten keine). Queued -> läuft
        // über die Cron-Queue, blockiert das Speichern nicht.
        $partner = $this->record->partner;

        if ($partner && filled($partner->email)) {
            Mail::to($partner->email)->send(new PartnerWelcomeMail($this->record));
        }
    }
}
