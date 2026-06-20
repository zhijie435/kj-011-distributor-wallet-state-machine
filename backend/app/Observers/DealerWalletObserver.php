<?php

namespace App\Observers;

use App\Jobs\WalletNotificationJob;
use App\Models\DealerWallet;

class DealerWalletObserver
{
    public function created(DealerWallet $wallet): void
    {
        \Illuminate\Support\Facades\Log::info('钱包已创建', [
            'wallet_id' => $wallet->id,
            'distributor_id' => $wallet->distributor_id,
            'wallet_no' => $wallet->wallet_no,
        ]);
    }

    public function updated(DealerWallet $wallet): void
    {
        if ($wallet->isDirty('status')) {
            $fromStatus = $wallet->getOriginal('status');
            $toStatus = $wallet->status;

            WalletNotificationJob::dispatch(
                $wallet->id,
                $fromStatus instanceof \BackedEnum ? $fromStatus->value : (string) $fromStatus,
                $toStatus instanceof \BackedEnum ? $toStatus->value : (string) $toStatus,
                match (true) {
                    $wallet->isDirty('freeze_reason') => $wallet->freeze_reason,
                    $wallet->isDirty('restrict_reason') => $wallet->restrict_reason,
                    $wallet->isDirty('close_reason') => $wallet->close_reason,
                    default => null,
                }
            );
        }
    }
}
