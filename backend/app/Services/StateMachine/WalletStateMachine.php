<?php

namespace App\Services\StateMachine;

use App\Contracts\StateMachine\StateMachineInterface;
use App\Contracts\StateMachine\TransitionResult;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WalletTransitionAction;
use App\Exceptions\StateTransitionException;
use App\Models\DealerWallet;
use App\Models\WalletStateLog;
use App\Models\WalletTransaction;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletStateMachine implements StateMachineInterface
{
    protected array $beforeHooks = [];

    protected array $afterHooks = [];

    public function __construct(
        protected DealerWallet $wallet,
    ) {
        $this->registerDefaultHooks();
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

        $rules = $this->buildValidationRules();

        foreach ($rules as $rule) {
            $result = $rule($currentStatus, $targetState, $this->wallet, $context);
            if ($result instanceof TransitionResult && $result->isInvalid()) {
                return $result;
            }
        }

        return TransitionResult::success('状态变更验证通过');
    }

    public function transitionTo(BackedEnum $targetState, array $context = []): DealerWallet
    {
        return DB::transaction(function () use ($targetState, $context) {
            $this->wallet = $this->wallet->lockForUpdate()->findOrFail($this->wallet->id);

            $fromStatus = $this->wallet->status;

            $validation = $this->validateTransition($targetState, $context);

            if ($validation->isInvalid()) {
                throw StateTransitionException::validationFailed(
                    $validation->message,
                    $validation->errors,
                    $this->wallet
                );
            }

            $action = $this->resolveAction($fromStatus, $targetState);

            $this->fireBeforeHooks($fromStatus, $targetState, $action, $context);

            $this->applyStateChange($fromStatus, $targetState, $action, $context);

            $this->logTransition($fromStatus, $targetState, $action, $context);

            $this->fireAfterHooks($fromStatus, $targetState, $action, $context);

            return $this->wallet->fresh();
        });
    }

    public function allowedTransitions(): array
    {
        return $this->wallet->status->allowedTransitions();
    }

    public function transitionByAction(WalletTransitionAction $action, array $context = []): DealerWallet
    {
        $currentStatus = $this->wallet->status;

        if ($action !== WalletTransitionAction::CLOSE) {
            $expectedFrom = $action->fromStatus();
            if ($currentStatus !== $expectedFrom) {
                throw StateTransitionException::invalidActionForState(
                    $action->label(),
                    $currentStatus->label(),
                    $expectedFrom->label(),
                    $this->wallet
                );
            }
        }

        return $this->transitionTo($action->toStatus(), $context);
    }

    public function beforeTransition(callable $hook): self
    {
        $this->beforeHooks[] = $hook;

        return $this;
    }

    public function afterTransition(callable $hook): self
    {
        $this->afterHooks[] = $hook;

        return $this;
    }

    protected function registerDefaultHooks(): void
    {
        $this->afterHooks[] = function (WalletStatus $from, WalletStatus $to, WalletTransitionAction $action, array $context) {
            $this->autoUnfreezeOnUnfreeze($to, $action, $context);
        };
    }

    protected function buildValidationRules(): array
    {
        return [
            function (WalletStatus $current, WalletStatus $target): ?TransitionResult {
                if ($current->isFinal()) {
                    return TransitionResult::failure(
                        "当前已处于终态（{$current->label()}），无法变更状态",
                        ['current_state' => $current->value, 'is_terminal' => true]
                    );
                }

                return null;
            },

            function (WalletStatus $current, WalletStatus $target, DealerWallet $wallet): ?TransitionResult {
                if ($target === WalletStatus::CLOSED && (float) $wallet->balance > 0) {
                    return TransitionResult::failure(
                        '钱包余额不为0，无法注销',
                        ['balance' => (float) $wallet->balance]
                    );
                }

                return null;
            },

            function (WalletStatus $current, WalletStatus $target, DealerWallet $wallet): ?TransitionResult {
                if ($target === WalletStatus::CLOSED && (float) $wallet->frozen_amount > 0) {
                    return TransitionResult::failure(
                        '钱包存在冻结金额，无法注销',
                        ['frozen_amount' => (float) $wallet->frozen_amount]
                    );
                }

                return null;
            },

            function (WalletStatus $current, WalletStatus $target): ?TransitionResult {
                if (!$current->canTransitionTo($target)) {
                    $allowedLabels = array_map(
                        fn(BackedEnum $s) => $s->label(),
                        $current->allowedTransitions()
                    );

                    return TransitionResult::failure(
                        "不允许从「{$current->label()}」变更为「{$target->label()}」",
                        [
                            'from_state' => $current->value,
                            'to_state' => $target->value,
                            'allowed_states' => $allowedLabels,
                        ]
                    );
                }

                return null;
            },
        ];
    }

    protected function applyStateChange(
        WalletStatus $fromStatus,
        WalletStatus $targetState,
        WalletTransitionAction $action,
        array $context
    ): void {
        $this->wallet->status = $targetState;

        $this->applyTimestamps($targetState);

        $this->applyReasons($fromStatus, $targetState, $context);

        $this->wallet->save();
    }

    protected function applyTimestamps(WalletStatus $targetState): void
    {
        $timestampMap = [
            WalletStatus::ACTIVE->value => 'last_activated_at',
            WalletStatus::FROZEN->value => 'last_frozen_at',
            WalletStatus::RESTRICTED->value => 'last_restricted_at',
            WalletStatus::CLOSED->value => 'closed_at',
        ];

        $field = $timestampMap[$targetState->value] ?? null;
        if ($field) {
            $this->wallet->$field = now();
        }
    }

    protected function applyReasons(WalletStatus $fromStatus, WalletStatus $targetState, array $context): void
    {
        $reason = $context['reason'] ?? null;

        $reasonSetterMap = [
            WalletStatus::FROZEN->value => 'freeze_reason',
            WalletStatus::RESTRICTED->value => 'restrict_reason',
            WalletStatus::CLOSED->value => 'close_reason',
        ];

        $setter = $reasonSetterMap[$targetState->value] ?? null;
        if ($setter) {
            $this->wallet->$setter = $reason;
        }

        $clearMap = $this->resolveReasonClearRules($fromStatus, $targetState);
        foreach ($clearMap as $field) {
            $this->wallet->$field = null;
        }
    }

    protected function resolveReasonClearRules(WalletStatus $from, WalletStatus $to): array
    {
        $rules = [
            WalletStatus::ACTIVE->value => [
                WalletStatus::FROZEN->value => ['restrict_reason'],
                WalletStatus::RESTRICTED->value => ['freeze_reason'],
                WalletStatus::CLOSED->value => ['freeze_reason', 'restrict_reason'],
                WalletStatus::ACTIVE->value => [],
            ],
            WalletStatus::FROZEN->value => [
                WalletStatus::ACTIVE->value => ['freeze_reason'],
                WalletStatus::CLOSED->value => ['freeze_reason', 'restrict_reason'],
            ],
            WalletStatus::RESTRICTED->value => [
                WalletStatus::ACTIVE->value => ['restrict_reason'],
                WalletStatus::FROZEN->value => ['restrict_reason'],
                WalletStatus::CLOSED->value => ['freeze_reason', 'restrict_reason'],
            ],
            WalletStatus::INACTIVE->value => [
                WalletStatus::ACTIVE->value => [],
                WalletStatus::CLOSED->value => ['freeze_reason', 'restrict_reason'],
            ],
            WalletStatus::CLOSED->value => [
                WalletStatus::CLOSED->value => ['freeze_reason', 'restrict_reason'],
            ],
        ];

        return $rules[$from->value][$to->value] ?? [];
    }

    protected function autoUnfreezeOnUnfreeze(WalletStatus $to, WalletTransitionAction $action, array $context): void
    {
        if ($action !== WalletTransitionAction::UNFREEZE) {
            return;
        }

        if ((float) $this->wallet->frozen_amount <= 0) {
            return;
        }

        $amount = (float) $this->wallet->frozen_amount;
        $operatorId = $context['operator_id'] ?? Auth::id();

        $this->wallet->frozen_amount = 0;
        $this->wallet->save();

        WalletTransaction::create([
            'wallet_id' => $this->wallet->id,
            'transaction_no' => 'UFR' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'type' => WalletTransactionType::UNFREEZE->value,
            'amount' => $amount,
            'balance_before' => (float) $this->wallet->balance,
            'balance_after' => (float) $this->wallet->balance,
            'currency' => $this->wallet->currency,
            'operator_id' => $operatorId,
            'remark' => '钱包解冻自动解冻冻结金额',
        ]);
    }

    protected function fireBeforeHooks(
        WalletStatus $fromStatus,
        WalletStatus $targetState,
        WalletTransitionAction $action,
        array $context
    ): void {
        foreach ($this->beforeHooks as $hook) {
            $hook($fromStatus, $targetState, $action, $context, $this->wallet);
        }
    }

    protected function fireAfterHooks(
        WalletStatus $fromStatus,
        WalletStatus $targetState,
        WalletTransitionAction $action,
        array $context
    ): void {
        foreach ($this->afterHooks as $hook) {
            $hook($fromStatus, $targetState, $action, $context, $this->wallet);
        }
    }

    protected function logTransition(
        WalletStatus $fromStatus,
        WalletStatus $toStatus,
        WalletTransitionAction $action,
        array $context
    ): WalletStateLog {
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
            default => throw StateTransitionException::invalidTransition($from->label(), $to->label(), [], $this->wallet),
        };
    }
}
