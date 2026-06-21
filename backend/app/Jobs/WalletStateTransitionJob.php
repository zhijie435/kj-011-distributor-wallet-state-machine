<?php

namespace App\Jobs;

use App\Enums\WalletTransitionAction;
use App\Models\DealerWallet;
use App\Services\StateMachine\WalletStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WalletStateTransitionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $walletId,
        public string $action,
        public array $context = [],
    ) {
        $this->onConnection(config('wallet.queue.connection'));
        $this->onQueue(config('wallet.queue.state_transition_queue'));
        $this->tries = config('wallet.queue.state_transition_tries', 3);
        $this->backoff = config('wallet.queue.state_transition_backoff', 30);
    }

    public function handle(): void
    {
        $wallet = DealerWallet::find($this->walletId);

        if (!$wallet) {
            Log::error('WalletStateTransitionJob: 钱包不存在', ['wallet_id' => $this->walletId]);

            return;
        }

        $action = WalletTransitionAction::from($this->action);
        $stateMachine = new WalletStateMachine($wallet);
        $stateMachine->transitionByAction($action, $this->context);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('WalletStateTransitionJob 执行失败', [
            'wallet_id' => $this->walletId,
            'action' => $this->action,
            'error' => $exception->getMessage(),
        ]);
    }
}
