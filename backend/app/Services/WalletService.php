<?php

namespace App\Services;

use App\Enums\WalletStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WalletTransitionAction;
use App\Exceptions\WalletException;
use App\Models\DealerWallet;
use App\Models\Distributor;
use App\Models\WalletTransaction;
use App\Repositories\WalletRepository;
use App\Repositories\WalletTransactionRepository;
use App\Services\StateMachine\WalletStateMachine;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function __construct(
        protected WalletRepository $walletRepository,
        protected WalletTransactionRepository $transactionRepository,
        protected ConfigRepository $config,
    ) {
    }

    public function createWallet(Distributor $distributor, ?int $operatorId = null): DealerWallet
    {
        $existingWallet = $this->walletRepository->findByDistributorId($distributor->id);
        if ($existingWallet) {
            throw WalletException::walletAlreadyExists($distributor->id, $existingWallet);
        }

        return DB::transaction(function () use ($distributor, $operatorId) {
            return $this->walletRepository->create([
                'distributor_id' => $distributor->id,
                'wallet_no' => $this->generateWalletNo(),
                'status' => WalletStatus::INACTIVE->value,
                'balance' => 0,
                'frozen_amount' => 0,
                'credit_limit' => $distributor->credit_limit,
                'currency' => 'CNY',
            ])->load(['distributor']);
        });
    }

    public function activateWallet(DealerWallet $wallet, string $reason = '', ?int $operatorId = null): DealerWallet
    {
        $stateMachine = $this->makeStateMachine($wallet);

        return $stateMachine->transitionByAction(WalletTransitionAction::ACTIVATE, [
            'reason' => $reason,
            'operator_id' => $operatorId,
        ])->load(['distributor']);
    }

    public function freezeWallet(DealerWallet $wallet, string $reason, ?int $operatorId = null): DealerWallet
    {
        $action = $wallet->isRestricted()
            ? WalletTransitionAction::FREEZE_FROM_RESTRICTED
            : WalletTransitionAction::FREEZE;

        $stateMachine = $this->makeStateMachine($wallet);

        return $stateMachine->transitionByAction($action, [
            'reason' => $reason,
            'operator_id' => $operatorId,
        ])->load(['distributor']);
    }

    public function unfreezeWallet(DealerWallet $wallet, string $reason = '', ?int $operatorId = null): DealerWallet
    {
        $stateMachine = $this->makeStateMachine($wallet);

        return $stateMachine->transitionByAction(WalletTransitionAction::UNFREEZE, [
            'reason' => $reason,
            'operator_id' => $operatorId,
        ])->load(['distributor']);
    }

    public function restrictWallet(DealerWallet $wallet, string $reason, ?int $operatorId = null): DealerWallet
    {
        $stateMachine = $this->makeStateMachine($wallet);

        return $stateMachine->transitionByAction(WalletTransitionAction::RESTRICT, [
            'reason' => $reason,
            'operator_id' => $operatorId,
        ])->load(['distributor']);
    }

    public function unrestrictWallet(DealerWallet $wallet, string $reason = '', ?int $operatorId = null): DealerWallet
    {
        $stateMachine = $this->makeStateMachine($wallet);

        return $stateMachine->transitionByAction(WalletTransitionAction::UNRESTRICT, [
            'reason' => $reason,
            'operator_id' => $operatorId,
        ])->load(['distributor']);
    }

    public function closeWallet(DealerWallet $wallet, string $reason, ?int $operatorId = null): DealerWallet
    {
        if ((float) $wallet->balance > 0) {
            throw WalletException::balanceNotZeroForClose((float) $wallet->balance, $wallet);
        }

        if ((float) $wallet->frozen_amount > 0) {
            throw WalletException::frozenAmountNotZeroForClose((float) $wallet->frozen_amount, $wallet);
        }

        $stateMachine = $this->makeStateMachine($wallet);

        return $stateMachine->transitionByAction(WalletTransitionAction::CLOSE, [
            'reason' => $reason,
            'operator_id' => $operatorId,
        ])->load(['distributor']);
    }

    public function recharge(DealerWallet $wallet, float $amount, string $remark = '', ?int $operatorId = null): WalletTransaction
    {
        $this->validatePositiveAmount($amount, $wallet, 'recharge');
        $this->validateMaxRecharge($amount, $wallet);

        return DB::transaction(function () use ($wallet, $amount, $remark, $operatorId) {
            $lockedWallet = $this->walletRepository->lockById($wallet->id);

            $this->validateOperationAllowed($lockedWallet, 'recharge');

            $balanceBefore = (float) $lockedWallet->balance;
            $balanceAfter = bcadd((string) $balanceBefore, (string) $amount, 2);

            $lockedWallet->balance = $balanceAfter;
            $lockedWallet->save();

            return $this->transactionRepository->create([
                'wallet_id' => $lockedWallet->id,
                'transaction_no' => $this->generateTransactionNo('RCH'),
                'type' => WalletTransactionType::RECHARGE->value,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'currency' => $lockedWallet->currency,
                'operator_id' => $operatorId,
                'remark' => $remark,
            ]);
        });
    }

    public function deduct(
        DealerWallet $wallet,
        float $amount,
        WalletTransactionType $type,
        string $remark = '',
        ?int $operatorId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): WalletTransaction {
        $this->validatePositiveAmount($amount, $wallet, 'deduct');

        if ($referenceType && $referenceId) {
            $this->validateNoDuplicateTransaction($referenceType, $referenceId, $wallet);
        }

        return DB::transaction(function () use ($wallet, $amount, $type, $remark, $operatorId, $referenceType, $referenceId) {
            $lockedWallet = $this->walletRepository->lockById($wallet->id);

            $this->validateOperationAllowed($lockedWallet, 'deduct');
            $this->validateSufficientBalance($lockedWallet, $amount);
            $this->validateMinBalanceAfterWithdraw($lockedWallet, $amount, 'deduct');

            $balanceBefore = (float) $lockedWallet->balance;
            $balanceAfter = bcsub((string) $balanceBefore, (string) $amount, 2);

            $lockedWallet->balance = $balanceAfter;
            $lockedWallet->save();

            return $this->transactionRepository->create([
                'wallet_id' => $lockedWallet->id,
                'transaction_no' => $this->generateTransactionNo('DED'),
                'type' => $type->value,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'currency' => $lockedWallet->currency,
                'operator_id' => $operatorId,
                'remark' => $remark,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    public function freezeAmount(DealerWallet $wallet, float $amount, string $remark = '', ?int $operatorId = null): WalletTransaction
    {
        $this->validatePositiveAmount($amount, $wallet, 'freezeAmount');

        return DB::transaction(function () use ($wallet, $amount, $remark, $operatorId) {
            $lockedWallet = $this->walletRepository->lockById($wallet->id);

            $this->validateOperationAllowed($lockedWallet, 'freezeAmount');
            $this->validateSufficientBalance($lockedWallet, $amount);

            $frozenBefore = (float) $lockedWallet->frozen_amount;
            $lockedWallet->frozen_amount = bcadd((string) $frozenBefore, (string) $amount, 2);
            $lockedWallet->save();

            return $this->transactionRepository->create([
                'wallet_id' => $lockedWallet->id,
                'transaction_no' => $this->generateTransactionNo('FRZ'),
                'type' => WalletTransactionType::FREEZE->value,
                'amount' => $amount,
                'balance_before' => (float) $lockedWallet->balance,
                'balance_after' => (float) $lockedWallet->balance,
                'currency' => $lockedWallet->currency,
                'operator_id' => $operatorId,
                'remark' => $remark,
            ]);
        });
    }

    public function unfreezeAmount(DealerWallet $wallet, float $amount, string $remark = '', ?int $operatorId = null): WalletTransaction
    {
        $this->validatePositiveAmount($amount, $wallet, 'unfreezeAmount');

        return DB::transaction(function () use ($wallet, $amount, $remark, $operatorId) {
            $lockedWallet = $this->walletRepository->lockById($wallet->id);

            if ($lockedWallet->isClosed()) {
                throw WalletException::closedWallet($lockedWallet);
            }

            $unfreezeAmount = min($amount, (float) $lockedWallet->frozen_amount);
            $lockedWallet->frozen_amount = bcsub((string) $lockedWallet->frozen_amount, (string) $unfreezeAmount, 2);
            $lockedWallet->save();

            return $this->transactionRepository->create([
                'wallet_id' => $lockedWallet->id,
                'transaction_no' => $this->generateTransactionNo('UFR'),
                'type' => WalletTransactionType::UNFREEZE->value,
                'amount' => $unfreezeAmount,
                'balance_before' => (float) $lockedWallet->balance,
                'balance_after' => (float) $lockedWallet->balance,
                'currency' => $lockedWallet->currency,
                'operator_id' => $operatorId,
                'remark' => $remark,
            ]);
        });
    }

    public function withdraw(
        DealerWallet $wallet,
        float $amount,
        string $remark = '',
        ?int $operatorId = null,
    ): WalletTransaction {
        $this->validatePositiveAmount($amount, $wallet, 'withdraw');
        $this->validateMaxWithdraw($amount, $wallet);

        return $this->deduct(
            $wallet,
            $amount,
            WalletTransactionType::WITHDRAW,
            $remark,
            $operatorId,
        );
    }

    public function getWalletBalance(DealerWallet $wallet): array
    {
        $stateMachine = $this->makeStateMachine($wallet);

        return [
            'wallet_id' => $wallet->id,
            'wallet_no' => $wallet->wallet_no,
            'distributor_id' => $wallet->distributor_id,
            'status' => $wallet->status->value,
            'status_label' => $wallet->status->label(),
            'status_color' => $wallet->status->color(),
            'is_active' => $wallet->isActive(),
            'is_frozen' => $wallet->isFrozen(),
            'is_restricted' => $wallet->isRestricted(),
            'is_inactive' => $wallet->isInactive(),
            'is_closed' => $wallet->isClosed(),
            'total_balance' => (float) $wallet->balance,
            'frozen_amount' => (float) $wallet->frozen_amount,
            'available_balance' => $wallet->getAvailableBalance(),
            'credit_limit' => (float) $wallet->credit_limit,
            'currency' => $wallet->currency,
            'freeze_reason' => $wallet->freeze_reason,
            'restrict_reason' => $wallet->restrict_reason,
            'close_reason' => $wallet->close_reason,
            'last_activated_at' => $wallet->last_activated_at?->toDateTimeString(),
            'last_frozen_at' => $wallet->last_frozen_at?->toDateTimeString(),
            'last_restricted_at' => $wallet->last_restricted_at?->toDateTimeString(),
            'closed_at' => $wallet->closed_at?->toDateTimeString(),
            'allowed_transitions' => $wallet->getAllowedTransitions(),
            'allowed_actions' => $this->resolveAllowedActions($stateMachine, $wallet),
            'can_activate' => $wallet->isInactive(),
            'can_freeze' => $stateMachine->canTransitionTo(WalletStatus::FROZEN),
            'can_unfreeze' => $stateMachine->canTransitionTo(WalletStatus::ACTIVE) && $wallet->isFrozen(),
            'can_restrict' => $stateMachine->canTransitionTo(WalletStatus::RESTRICTED),
            'can_unrestrict' => $stateMachine->canTransitionTo(WalletStatus::ACTIVE) && $wallet->isRestricted(),
            'can_close' => $stateMachine->canTransitionTo(WalletStatus::CLOSED)
                && (float) $wallet->balance == 0
                && (float) $wallet->frozen_amount == 0,
        ];
    }

    public function getWalletTransactions(DealerWallet $wallet, array $params = []): LengthAwarePaginator
    {
        return $this->walletRepository->getTransactions($wallet, $params);
    }

    public function getWalletStateLogs(DealerWallet $wallet, array $params = []): LengthAwarePaginator
    {
        return $this->walletRepository->getStateLogs($wallet, $params);
    }

    public function getStatistics(DealerWallet $wallet, array $params = []): array
    {
        $repoStats = $this->walletRepository->getStatistics($wallet, $params);
        $period = $repoStats['period'];

        return [
            'period' => $period,
            'income' => (float) $repoStats['income'],
            'expense' => (float) $repoStats['expense'],
            'net_flow' => (float) $repoStats['net_flow'],
            'transaction_count' => (int) ($repoStats['transaction_count'] ?? 0),
            'income_details' => $this->transactionRepository->getAggregateStats($wallet->id, $period['start_date'], $period['end_date']),
        ];
    }

    public function listWallets(array $params = []): LengthAwarePaginator
    {
        return $this->walletRepository->list($params);
    }

    protected function makeStateMachine(DealerWallet $wallet): WalletStateMachine
    {
        return new WalletStateMachine($wallet);
    }

    protected function validatePositiveAmount(float $amount, ?DealerWallet $wallet = null, string $operation = ''): void
    {
        if ($amount <= 0) {
            throw WalletException::invalidAmount($amount, $operation, $wallet);
        }
    }

    protected function validateOperationAllowed(DealerWallet $wallet, string $operation): void
    {
        if ($wallet->isClosed()) {
            throw WalletException::closedWallet($wallet);
        }

        if ($wallet->isFrozen()) {
            throw WalletException::frozenWallet($wallet);
        }

        if ($wallet->isRestricted()) {
            throw WalletException::restrictedWallet($wallet);
        }
    }

    protected function validateSufficientBalance(DealerWallet $wallet, float $amount): void
    {
        $available = $wallet->getAvailableBalance();
        if ($amount > $available) {
            throw WalletException::insufficientBalance($amount, $available, $wallet);
        }
    }

    protected function validateMaxRecharge(float $amount, DealerWallet $wallet): void
    {
        $maxRecharge = (float) $this->config->get('wallet.max_single_recharge', 500000);
        if ($amount > $maxRecharge) {
            throw WalletException::exceedsMaxSingleRecharge($amount, $maxRecharge, $wallet);
        }
    }

    protected function validateMaxWithdraw(float $amount, DealerWallet $wallet): void
    {
        $maxWithdraw = (float) $this->config->get('wallet.max_single_withdraw', 200000);
        if ($amount > $maxWithdraw) {
            throw WalletException::exceedsMaxSingleWithdraw($amount, $maxWithdraw, $wallet);
        }
    }

    protected function validateMinBalanceAfterWithdraw(DealerWallet $wallet, float $deductAmount, string $operation): void
    {
        $minBalance = (float) $this->config->get('wallet.min_balance', 0);
        if ($minBalance <= 0) {
            return;
        }

        $afterBalance = bcsub((string) $wallet->balance, (string) $deductAmount, 2);
        if ((float) $afterBalance < $minBalance) {
            throw WalletException::belowMinBalance((float) $afterBalance, $minBalance, $wallet, $operation);
        }
    }

    protected function validateNoDuplicateTransaction(
        string $referenceType,
        int $referenceId,
        DealerWallet $wallet,
    ): void {
        $existing = $this->transactionRepository->findByReference($referenceType, $referenceId);
        if ($existing) {
            throw WalletException::duplicateTransaction($referenceType, $referenceId, $wallet);
        }
    }

    protected function resolveAllowedActions(WalletStateMachine $stateMachine, DealerWallet $wallet): array
    {
        $actions = [];
        $status = $wallet->status;

        $actionMap = [
            WalletStatus::INACTIVE->value => [
                WalletTransitionAction::ACTIVATE,
            ],
            WalletStatus::ACTIVE->value => [
                WalletTransitionAction::FREEZE,
                WalletTransitionAction::RESTRICT,
            ],
            WalletStatus::FROZEN->value => [
                WalletTransitionAction::UNFREEZE,
            ],
            WalletStatus::RESTRICTED->value => [
                WalletTransitionAction::UNRESTRICT,
                WalletTransitionAction::FREEZE_FROM_RESTRICTED,
            ],
        ];

        $candidates = $actionMap[$status->value] ?? [];
        foreach ($candidates as $action) {
            $target = $action->toStatus();
            if ($stateMachine->canTransitionTo($target)) {
                $actions[] = [
                    'action' => $action->value,
                    'label' => $action->label(),
                    'target_status' => $target->value,
                    'target_status_label' => $target->label(),
                ];
            }
        }

        if ($stateMachine->canTransitionTo(WalletStatus::CLOSED)
            && (float) $wallet->balance === 0.0
            && (float) $wallet->frozen_amount === 0.0
        ) {
            $actions[] = [
                'action' => WalletTransitionAction::CLOSE->value,
                'label' => WalletTransitionAction::CLOSE->label(),
                'target_status' => WalletStatus::CLOSED->value,
                'target_status_label' => WalletStatus::CLOSED->label(),
            ];
        }

        return $actions;
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
