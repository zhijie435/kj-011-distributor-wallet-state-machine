<?php

namespace App\Jobs;

use App\Enums\WalletStatus;
use App\Models\DealerWallet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WalletNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $walletId,
        public string $fromStatus,
        public string $toStatus,
        public ?string $reason = null,
    ) {
    }

    public function handle(): void
    {
        $wallet = DealerWallet::with('distributor')->find($this->walletId);

        if (!$wallet) {
            return;
        }

        $toStatus = WalletStatus::from($this->toStatus);

        Log::info('钱包状态变更通知', [
            'wallet_id' => $this->walletId,
            'distributor' => $wallet->distributor?->name,
            'from' => $this->fromStatus,
            'to' => $this->toStatus,
            'reason' => $this->reason,
        ]);
    }
}
