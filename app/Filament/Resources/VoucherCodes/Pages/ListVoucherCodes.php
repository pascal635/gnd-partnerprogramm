<?php

namespace App\Filament\Resources\VoucherCodes\Pages;

use App\Enums\SyncStatus;
use App\Filament\Resources\VoucherCodes\VoucherCodeResource;
use App\Jobs\SyncVoucherToWordPress;
use App\Models\VoucherCode;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListVoucherCodes extends ListRecords
{
    protected static string $resource = VoucherCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncAll')
                ->label('Alle an WordPress senden')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Alle Codes synchronisieren')
                ->modalDescription('Alle aktiven Gutscheincodes werden (erneut) an WordPress gesendet — nützlich, wenn WordPress noch keine Codes hat.')
                ->action(function (): void {
                    $codes = VoucherCode::query()->where('is_active', true)->get();
                    $failed = 0;

                    foreach ($codes as $code) {
                        $code->update(['sync_status' => SyncStatus::Pending]);

                        try {
                            SyncVoucherToWordPress::dispatchSync($code);
                        } catch (\Throwable $e) {
                            $code->update(['sync_status' => SyncStatus::Failed]);
                            $failed++;
                        }
                    }

                    $ok = $codes->count() - $failed;

                    $notification = Notification::make()
                        ->title("{$ok} von {$codes->count()} Code(s) an WordPress gesendet")
                        ->body($failed > 0 ? "{$failed} fehlgeschlagen – bitte einzeln erneut senden." : 'Alle erfolgreich übertragen.');

                    if ($failed > 0) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
            CreateAction::make(),
        ];
    }
}
