<?php

namespace App\Services\StateMachine;

use App\Contracts\StateMachine\StateMachineInterface;
use App\Contracts\StateMachine\TransitionResult;
use App\Enums\WalletStatus;
use BackedEnum;
use App\Enums\WalletTransitionAction;
use App\Exceptions\StateTransitionException;
use App\Models\DealerWallet;
use App\Models\WalletStateLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletStateMachine implements StateMachineInterface
{
    public function __construct(
        protected DealerWallet $wallet,
    ) {
    }

    public function getModel(): Model
    {
        return $this->wallet;
    }

    public function currentState(): BackedEnum
    {
        return $this->wallet->status;
    }

    public function canTransitionTo(BackedEnum $targetState, array $context = []): bool
    {
        return $this->wallet->status->canTransitionTo($targetState);
    }

    public function validateTransition(BackedEnum $targetState, array $context = []): TransitionResult
    {
        $currentStatus = $this->wallet->status;

        if ($currentStatus->isFinal()) {
            return TransitionResult::failure(
                "当前已处于终态（{$currentStatus->label()}），无法变更状态",
                ['current_state' => $currentStatus->value, 'is_terminal' => true]
            );
        }

        if (!$this->canTransitionTo($targetState)) {
            $allowedLabels = array_map(
                fn(BackedEnum $s) => $s->label(),
                $currentStatus->allowedTransitions()
            );

            return TransitionResult::failure(
                "不允许从「{$currentStatus->label()}」变更为「{$targetState->label()}」",
                [
                    'from_state' => $currentStatus->value,
                    'to_state' => $targetState->value,
                    'allowed_states' => $allowedLabels,
                ]
            );
        }

        if ($targetState === WalletStatus::CLOSED) {
            if ((float) $this->wallet->balance > 0) {
                return TransitionResult::failure(
                    '钱包余额不为0，无法注销',
                    ['balance' => (float) $this->wallet->balance]
                );
            }

            if ((float) $this->wallet->frozen_amount > 0) {
                return TransitionResult::failure(
                    '钱包存在冻结金额，无法注销',
                    ['frozen_amount' => (float) $this->wallet->frozen_amount]
                );
            }
        }

        return TransitionResult::success('状态变更验证通过');
    }

    public function transitionTo(BackedEnum $targetState, array $context = []): DealerWallet
    {
        $validation = $this->validateTransition($targetState, $context);

        if ($validation->isInvalid()) {
            throw StateTransitionException::invalidTransition(
                $this->wallet->status->label(),
                $targetState->label(),
                array_map(fn(WalletStatus $s) => $s->label(), $this->wallet->status->allowedTransitions())
            );
        }

        return DB::transaction(function () use ($targetState, $context) {
            $fromStatus = $this->wallet->status;

            $this->wallet->status = $targetState;

            $this->updateStatusTimestamps($targetState, $context);

            $this->wallet->save();

            $this->logTransition($fromStatus, $targetState, $context);

            return $this->wallet->fresh();
        });
    }

    public function allowedTransitions(): array
    {
        return $this->wallet->status->allowedTransitions();
    }

    public function transitionByAction(WalletTransitionAction $action, array $context = []): DealerWallet
    {
        return $this->transitionTo($action->toStatus(), $context);
    }

    protected function updateStatusTimestamps(WalletStatus $targetState, array $context): void
    {
        match ($targetState) {
            WalletStatus::ACTIVE => $this->wallet->last_activated_at = now(),
            WalletStatus::FROZEN => $this->wallet->last_frozen_at = now(),
            WalletStatus::RESTRICTED => $this->wallet->last_restricted_at = now(),
            WalletStatus::CLOSED => $this->wallet->closed_at = now(),
            default => null,
        };

        match ($targetState) {
            WalletStatus::FROZEN => $this->wallet->freeze_reason = $context['reason'] ?? null,
            WalletStatus::RESTRICTED => $this->wallet->restrict_reason = $context['reason'] ?? null,
            WalletStatus::CLOSED => $this->wallet->close_reason = $context['reason'] ?? null,
            default => null,
        };

        if ($targetState === WalletStatus::ACTIVE && $this->wallet->isFrozen()) {
            $this->wallet->freeze_reason = null;
        }

        if ($targetState === WalletStatus::ACTIVE && $this->wallet->isRestricted()) {
            $this->wallet->restrict_reason = null;
        }
    }

    protected function logTransition(WalletStatus $fromStatus, WalletStatus $toStatus, array $context): WalletStateLog
    {
        $action = $this->resolveAction($fromStatus, $toStatus);

        return WalletStateLog::create([
            'wallet_id' => $this->wallet->id,
            'from_status' => $fromStatus->value,
            'to_status' => $toStatus->value,
            'action' => $action->value,
            'operator_id' => $context['operator_id'] ?? Auth::id(),
            'reason' => $context['reason'] ?? null,
            'context' => $context,
            'created_at' => now(),
        ]);
    }

    protected function resolveAction(WalletStatus $from, WalletStatus $to): WalletTransitionAction
    {
        return match (true) {
            $from === WalletStatus::INACTIVE && $to === WalletStatus::ACTIVE => WalletTransitionAction::ACTIVATE,
            $from === WalletStatus::ACTIVE && $to === WalletStatus::FROZEN => WalletTransitionAction::FREEZE,
            $from === WalletStatus::FROZEN && $to === WalletStatus::ACTIVE => WalletTransitionAction::UNFREEZE,
            $from === WalletStatus::ACTIVE && $to === WalletStatus::RESTRICTED => WalletTransitionAction::RESTRICT,
            $from === WalletStatus::RESTRICTED && $to === WalletStatus::ACTIVE => WalletTransitionAction::UNRESTRICT,
            $from === WalletStatus::RESTRICTED && $to === WalletStatus::FROZEN => WalletTransitionAction::FREEZE_FROM_RESTRICTED,
            $to === WalletStatus::CLOSED => WalletTransitionAction::CLOSE,
            default => throw StateTransitionException::invalidTransition($from->label(), $to->label()),
        };
    }
}
