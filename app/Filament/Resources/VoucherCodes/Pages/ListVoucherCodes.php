<?php

namespace App\Filament\Resources\VoucherCodes\Pages;

use App\Enums\SyncStatus;
use App\Filament\Resources\VoucherCodes\VoucherCodeResource;
use App\Jobs\SyncVoucherToWordPress;
use App\Models\VoucherCode;
use App\Services\Attribution\LateCodeAttribution;
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
            Action::make('backfillLeads')
                ->label('Anfragen ohne Partner zuordnen')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Anfragen nachträglich zuordnen')
                ->modalDescription('Anfragen ohne Partner, bei denen ein Code eingegeben wurde, der inzwischen angelegt ist, werden dem Partner dieses Codes zugeordnet. Provisionen werden dabei berechnet. Bereits zugeordnete Anfragen bleiben unverändert.')
                ->action(function (): void {
                    $count = app(LateCodeAttribution::class)->backfillAll();

                    LateCodeAttribution::notifyBackfilled($count, always: true);
                }),
            CreateAction::make(),
        ];
    }
}
