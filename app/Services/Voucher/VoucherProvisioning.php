<?php

namespace App\Services\Voucher;

use App\Enums\SyncStatus;
use App\Jobs\SyncVoucherToWordPress;
use App\Mail\PartnerWelcomeMail;
use App\Models\VoucherCode;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Side effects after a voucher code is created: push it to WordPress and send
 * the partner's welcome mail. Failures never undo the code; they are returned
 * as human-readable warnings for the UI.
 */
class VoucherProvisioning
{
    /** @return array<int, array{title: string, body: string}> */
    public function afterCreate(VoucherCode $voucher): array
    {
        $warnings = [];

        // Synchronous (no cron on the shared host).
        try {
            SyncVoucherToWordPress::dispatchSync($voucher);
        } catch (Throwable) {
            $voucher->update(['sync_status' => SyncStatus::Failed->value]);
            $warnings[] = [
                'title' => 'WordPress-Sync fehlgeschlagen',
                'body' => 'Der Code wurde gespeichert, aber nicht an WordPress übertragen. Bitte später „Erneut senden".',
            ];
        }

        // Welcome mail only for partner codes with an email address.
        $partner = $voucher->partner;

        if ($partner && filled($partner->email)) {
            try {
                Mail::to($partner->email)->send(new PartnerWelcomeMail($voucher));
            } catch (Throwable) {
                $warnings[] = [
                    'title' => 'Willkommens-Mail konnte nicht gesendet werden',
                    'body' => 'Der Code wurde gespeichert. Bitte die E-Mail-Einstellungen prüfen.',
                ];
            }
        }

        return $warnings;
    }
}
