<?php

namespace App\Exceptions;

use App\Enums\WalletStatus;
use App\Enums\WalletTransitionAction;
use App\Models\DealerWallet;

class StateTransitionException extends BaseException
{
    protected int $httpCode = 422;

    protected string $errorCode = 'STATE_TRANSITION_ERROR';

    protected const ERROR_CODES = [
        'INVALID_TRANSITION' => 'INVALID_STATE_TRANSITION',
        'TERMINAL_STATE' => 'TERMINAL_STATE_REACHED',
        'VALIDATION_FAILED' => 'STATE_TRANSITION_VALIDATION_FAILED',
        'RULE_VIOLATION' => 'STATE_TRANSITION_RULE_VIOLATION',
        'INVALID_ACTION_FOR_STATE' => 'INVALID_ACTION_FOR_CURRENT_STATE',
    ];

    public function __construct(
        string $message,
        array $details = [],
        int $httpCode = 422,
        ?DealerWallet $wallet = null,
        ?WalletTransitionAction $action = null,
    ) {
        parent::__construct($message);
        $this->details = $this->enrichDetails($details, $wallet, $action);
        $this->httpCode = $httpCode;
    }

    protected function enrichDetails(
        array $details,
        ?DealerWallet $wallet,
        ?WalletTransitionAction $action
    ): array {
        if ($wallet) {
            $details += [
                'wallet_id' => $wallet->id,
                'wallet_no' => $wallet->wallet_no,
                'distributor_id' => $wallet->distributor_id,
                'current_status' => $wallet->status->value,
                'current_status_label' => $wallet->status->label(),
            ];
        }

        if ($action) {
            $details += [
                'action' => $action->value,
                'action_label' => $action->label(),
            ];
        }

        $details['error_code'] = $this->errorCode;

        return $details;
    }

    protected static function make(
        string $message,
        string $errorCodeKey,
        array $details = [],
        int $httpCode = 422,
        ?DealerWallet $wallet = null,
        ?WalletTransitionAction $action = null,
    ): self {
        $instance = new self($message, $details, $httpCode, $wallet, $action);
        $instance->errorCode = self::ERROR_CODES[$errorCodeKey] ?? $errorCodeKey;
        $instance->details['error_code'] = $instance->errorCode;

        return $instance;
    }

    public static function invalidTransition(
        string $fromState,
        string $toState,
        array $allowedStates = [],
        ?DealerWallet $wallet = null,
    ): self {
        $message = sprintf(
            '不允许从「%s」变更为「%s」',
            $fromState,
            $toState,
        );

        if (!empty($allowedStates)) {
            $message .= '，允许的目标状态：'.implode('、', $allowedStates);
        }

        return self::make($message, 'INVALID_TRANSITION', [
            'from_state' => $fromState,
            'to_state' => $toState,
            'allowed_states' => $allowedStates,
        ], 422, $wallet);
    }

    public static function terminalState(WalletStatus $state, ?DealerWallet $wallet = null): self
    {
        return self::make(
            "当前已处于终态（{$state->label()}），无法变更状态",
            'TERMINAL_STATE',
            [
                'current_state' => $state->value,
                'current_state_label' => $state->label(),
                'is_terminal' => true,
            ],
            422,
            $wallet
        );
    }

    public static function validationFailed(
        string $message,
        array $errors = [],
        ?DealerWallet $wallet = null,
        ?WalletTransitionAction $action = null,
    ): self {
        return self::make($message, 'VALIDATION_FAILED', $errors, 422, $wallet, $action);
    }

    public static function ruleViolation(
        string $rule,
        string $message,
        array $context = [],
        ?DealerWallet $wallet = null,
        ?WalletTransitionAction $action = null,
    ): self {
        return self::make($message, 'RULE_VIOLATION', [
            'rule' => $rule,
            'context' => $context,
        ], 422, $wallet, $action);
    }

    public static function invalidActionForState(
        string $actionLabel,
        string $currentStateLabel,
        string $expectedStateLabel,
        ?DealerWallet $wallet = null,
        ?WalletTransitionAction $action = null,
    ): self {
        $message = sprintf(
            '动作「%s」仅适用于「%s」状态，当前状态为「%s」',
            $actionLabel,
            $expectedStateLabel,
            $currentStateLabel,
        );

        return self::make($message, 'INVALID_ACTION_FOR_STATE', [
            'action_label' => $actionLabel,
            'current_state_label' => $currentStateLabel,
            'expected_state_label' => $expectedStateLabel,
        ], 422, $wallet, $action);
    }
}
