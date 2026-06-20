<?php

namespace App\Services;

use App\Enums\WalletStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WalletTransitionAction;
use App\Exceptions\WalletException;
use App\Models\DealerWallet;
use App\Models\Distributor;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\StateMachine\WalletStateMachine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function createWallet(Distributor $distributor, ?int $operatorId = null): DealerWallet
    {
        if ($distributor->wallet) {
            throw WalletException::walletAlreadyExists($distributor->id);
        }

        return DB::transaction(function () use ($distributor, $operatorId) {
            $wallet = DealerWallet::create([
                'distributor_id' => $distributor->id,
                'wallet_no' => $this->generateWalletNo(),
                'status' => WalletStatus::INACTIVE->value,
                'balance' => 0,
                'frozen_amount' => 0,
                'credit_limit' => $distributor->credit_limit,
                'currency' => 'CNY',
            ]);

            return $wallet;
        });
    }

    public function activateWallet(DealerWallet $wallet, string $reason = '', ?int $operatorId = null): DealerWallet
    {
        $stateMachine = new WalletStateMachine($wallet);

        return $stateMachine->transitionByAction(WalletTransitionAction::ACTIVATE, [
            'reason' => $reason,
            'operator_id' => $operatorId,
        ]);
    }

    public function freezeWallet(DealerWallet $wallet, string $reason, ?int $operatorId = null): DealerWallet
    {
        $stateMachine = new WalletStateMachine($wallet);

        return $stateMachine->transitionByAction(
            $wallet->isRestricted()
                ? WalletTransitionAction::FREEZE_FROM_RESTRICTED
                : WalletTransitionAction::FREEZE,
            [
                'reason' => $reason,
                'operator_id' => $operatorId,
            ]
        );
    }

    public function unfreezeWallet(DealerWallet $wallet, string $reason = '', ?int $operatorId = null): DealerWallet
    {
        return DB::transaction(function () use ($wallet, $reason, $operatorId) {
            if ((float) $wallet->frozen_amount > 0) {
                $this->unfreezeAmount($wallet, (float) $wallet->frozen_amount, '钱包解冻自动解冻冻结金额', $operatorId);
            }

            $stateMachine = new WalletStateMachine($wallet);

            return $stateMachine->transitionByAction(WalletTransitionAction::UNFREEZE, [
                'reason' => $reason,
                'operator_id' => $operatorId,
            ]);
        });
    }

    public function restrictWallet(DealerWallet $wallet, string $reason, ?int $operatorId = null): DealerWallet
    {
        $stateMachine = new WalletStateMachine($wallet);

        return $stateMachine->transitionByAction(WalletTransitionAction::RESTRICT, [
            'reason' => $reason,
            'operator_id' => $operatorId,
        ]);
    }

    public function unrestrictWallet(DealerWallet $wallet, string $reason = '', ?int $operatorId = null): DealerWallet
    {
        $stateMachine = new WalletStateMachine($wallet);

        return $stateMachine->transitionByAction(WalletTransitionAction::UNRESTRICT, [
            'reason' => $reason,
            'operator_id' => $operatorId,
        ]);
    }

    public function closeWallet(DealerWallet $wallet, string $reason, ?int $operatorId = null): DealerWallet
    {
        if ((float) $wallet->balance > 0) {
            throw WalletException::insufficientBalance($wallet->balance, 0);
        }

        if ((float) $wallet->frozen_amount > 0) {
            throw WalletException::frozenWallet();
        }

        $stateMachine = new WalletStateMachine($wallet);

        return $stateMachine->transitionByAction(WalletTransitionAction::CLOSE, [
            'reason' => $reason,
            'operator_id' => $operatorId,
        ]);
    }

    public function recharge(DealerWallet $wallet, float $amount, string $remark = '', ?int $operatorId = null): WalletTransaction
    {
        $this->validateOperation($wallet, $amount);

        return DB::transaction(function () use ($wallet, $amount, $remark, $operatorId) {
            $wallet = $wallet->lockForUpdate()->findOrFail($wallet->id);

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = bcadd((string) $balanceBefore, (string) $amount, 2);

            $wallet->balance = $balanceAfter;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_no' => $this->generateTransactionNo('RCH'),
                'type' => WalletTransactionType::RECHARGE->value,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'currency' => $wallet->currency,
                'operator_id' => $operatorId,
                'remark' => $remark,
            ]);
        });
    }

    public function deduct(DealerWallet $wallet, float $amount, WalletTransactionType $type, string $remark = '', ?int $operatorId = null, ?string $referenceType = null, ?int $referenceId = null): WalletTransaction
    {
        $this->validateOperation($wallet, $amount);

        if (!$wallet->hasSufficientBalance($amount)) {
            throw WalletException::insufficientBalance($amount, $wallet->getAvailableBalance());
        }

        return DB::transaction(function () use ($wallet, $amount, $type, $remark, $operatorId, $referenceType, $referenceId) {
            $wallet = $wallet->lockForUpdate()->findOrFail($wallet->id);

            if (!$wallet->hasSufficientBalance($amount)) {
                throw WalletException::insufficientBalance($amount, $wallet->getAvailableBalance());
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = bcsub((string) $balanceBefore, (string) $amount, 2);

            $wallet->balance = $balanceAfter;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_no' => $this->generateTransactionNo('DED'),
                'type' => $type->value,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'currency' => $wallet->currency,
                'operator_id' => $operatorId,
                'remark' => $remark,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    public function freezeAmount(DealerWallet $wallet, float $amount, string $remark = '', ?int $operatorId = null): WalletTransaction
    {
        $this->validateOperation($wallet, $amount);

        if (!$wallet->hasSufficientBalance($amount)) {
            throw WalletException::insufficientBalance($amount, $wallet->getAvailableBalance());
        }

        return DB::transaction(function () use ($wallet, $amount, $remark, $operatorId) {
            $wallet = $wallet->lockForUpdate()->findOrFail($wallet->id);

            $frozenBefore = (float) $wallet->frozen_amount;
            $wallet->frozen_amount = bcadd((string) $frozenBefore, (string) $amount, 2);
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_no' => $this->generateTransactionNo('FRZ'),
                'type' => WalletTransactionType::FREEZE->value,
                'amount' => $amount,
                'balance_before' => (float) $wallet->balance,
                'balance_after' => (float) $wallet->balance,
                'currency' => $wallet->currency,
                'operator_id' => $operatorId,
                'remark' => $remark,
            ]);
        });
    }

    public function unfreezeAmount(DealerWallet $wallet, float $amount, string $remark = '', ?int $operatorId = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $remark, $operatorId) {
            $wallet = $wallet->lockForUpdate()->findOrFail($wallet->id);

            $unfreezeAmount = min($amount, (float) $wallet->frozen_amount);
            $wallet->frozen_amount = bcsub((string) $wallet->frozen_amount, (string) $unfreezeAmount, 2);
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_no' => $this->generateTransactionNo('UFR'),
                'type' => WalletTransactionType::UNFREEZE->value,
                'amount' => $unfreezeAmount,
                'balance_before' => (float) $wallet->balance,
                'balance_after' => (float) $wallet->balance,
                'currency' => $wallet->currency,
                'operator_id' => $operatorId,
                'remark' => $remark,
            ]);
        });
    }

    public function getWalletBalance(DealerWallet $wallet): array
    {
        return [
            'wallet_id' => $wallet->id,
            'wallet_no' => $wallet->wallet_no,
            'status' => $wallet->status->value,
            'status_label' => $wallet->status->label(),
            'total_balance' => (float) $wallet->balance,
            'frozen_amount' => (float) $wallet->frozen_amount,
            'available_balance' => $wallet->getAvailableBalance(),
            'credit_limit' => (float) $wallet->credit_limit,
            'currency' => $wallet->currency,
        ];
    }

    public function getWalletTransactions(DealerWallet $wallet, array $params = []): LengthAwarePaginator
    {
        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->with(['operator']);

        if (isset($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (isset($params['start_date'])) {
            $query->whereDate('created_at', '>=', $params['start_date']);
        }

        if (isset($params['end_date'])) {
            $query->whereDate('created_at', '<=', $params['end_date']);
        }

        return $query->orderBy('id', 'desc')->paginate($params['per_page'] ?? 20);
    }

    public function getWalletStateLogs(DealerWallet $wallet, array $params = []): LengthAwarePaginator
    {
        $query = $wallet->stateLogs()->with(['operator']);

        return $query->orderBy('id', 'desc')->paginate($params['per_page'] ?? 20);
    }

    public function getStatistics(DealerWallet $wallet, array $params = []): array
    {
        $startDate = $params['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $params['end_date'] ?? now()->endOfMonth()->toDateString();

        $income = (float) WalletTransaction::where('wallet_id', $wallet->id)
            ->whereIn('type', [
                WalletTransactionType::RECHARGE->value,
                WalletTransactionType::REFUND->value,
                WalletTransactionType::TRANSFER_IN->value,
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $expense = abs((float) WalletTransaction::where('wallet_id', $wallet->id)
            ->whereIn('type', [
                WalletTransactionType::WITHDRAW->value,
                WalletTransactionType::PAYMENT->value,
                WalletTransactionType::TRANSFER_OUT->value,
                WalletTransactionType::FEE->value,
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount'));

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'income' => $income,
            'expense' => $expense,
            'net_flow' => $income - $expense,
        ];
    }

    public function listWallets(array $params = []): LengthAwarePaginator
    {
        $query = DealerWallet::with(['distributor']);

        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (isset($params['distributor_id'])) {
            $query->where('distributor_id', $params['distributor_id']);
        }

        if (isset($params['search'])) {
            $query->whereHas('distributor', function ($q) use ($params) {
                $q->where('name', 'like', "%{$params['search']}%")
                    ->orWhere('company_name', 'like', "%{$params['search']}%");
            });
        }

        return $query->orderBy('id', 'desc')->paginate($params['per_page'] ?? 20);
    }

    protected function validateOperation(DealerWallet $wallet, float $amount): void
    {
        if ($amount <= 0) {
            throw WalletException::invalidAmount($amount);
        }

        if ($wallet->isClosed()) {
            throw WalletException::closedWallet();
        }

        if ($wallet->isFrozen()) {
            throw WalletException::frozenWallet();
        }
    }

    protected function generateWalletNo(): string
    {
        return 'W' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    protected function generateTransactionNo(string $prefix = 'TXN'): string
    {
        return $prefix . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
