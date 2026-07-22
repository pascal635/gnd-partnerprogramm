<?php

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Models\VoucherCode;
use App\Services\Voucher\VoucherSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncVoucherToWordPress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public function __construct(public VoucherCode $voucher) {}

    /** Exponential-ish backoff spread over hours (seconds). */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600, 7200, 14400];
    }

    public function handle(VoucherSyncService $service): void
    {
        $service->push($this->voucher);
    }

    public function failed(?Throwable $e): void
    {
        $this->voucher->update(['sync_status' => SyncStatus::Failed]);
    }
}
