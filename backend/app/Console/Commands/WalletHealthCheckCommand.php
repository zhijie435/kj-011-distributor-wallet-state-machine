<?php

namespace App\Console\Commands;

use App\Enums\WalletStatus;
use App\Models\DealerWallet;
use Illuminate\Console\Command;

class WalletHealthCheckCommand extends Command
{
    protected $signature = 'wallet:health-check';

    protected $description = '检查钱包状态机健康状态';

    public function handle(): int
    {
        $this->info('=== 钱包状态机健康检查 ===');

        $total = DealerWallet::count();
        $this->info("钱包总数: {$total}");

        foreach (WalletStatus::cases() as $status) {
            $count = DealerWallet::where('status', $status->value)->count();
            $this->line("  {$status->label()}: {$count}");
        }

        $negativeBalance = DealerWallet::where('balance', '<', 0)->count();
        if ($negativeBalance > 0) {
            $this->warn("余额为负的钱包: {$negativeBalance}");
        }

        $inconsistentFreeze = DealerWallet::where('frozen_amount', '>', 0)
            ->whereColumn('frozen_amount', '>', 'balance')
            ->count();
        if ($inconsistentFreeze > 0) {
            $this->warn("冻结金额超过余额的钱包: {$inconsistentFreeze}");
        }

        $inactiveWithBalance = DealerWallet::where('status', WalletStatus::INACTIVE->value)
            ->where('balance', '>', 0)
            ->count();
        if ($inactiveWithBalance > 0) {
            $this->warn("未激活但有余额的钱包: {$inactiveWithBalance}");
        }

        $this->info('=== 健康检查完成 ===');

        return self::SUCCESS;
    }
}
