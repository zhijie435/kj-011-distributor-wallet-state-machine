<?php

namespace App\Exceptions;

class StateTransitionException extends BaseException
{
    protected int $httpCode = 422;

    protected string $errorCode = 'STATE_TRANSITION_ERROR';

    public function __construct(string $message, array $details = [])
    {
        parent::__construct($message);
        $this->details = $details;
    }

    public static function invalidTransition(
        string $fromState,
        string $toState,
        array $allowedStates = [],
    ): self {
        $message = sprintf(
            '不允许从「%s」变更为「%s」',
            $fromState,
            $toState,
        );

        if (!empty($allowedStates)) {
            $message .= '，允许的目标状态：'.implode('、', $allowedStates);
        }

        return new self($message, [
            'from_state' => $fromState,
            'to_state' => $toState,
            'allowed_states' => $allowedStates,
        ]);
    }

    public static function terminalState(string $state): self
    {
        return new self("当前已处于终态（{$state}），无法变更状态", [
            'current_state' => $state,
            'is_terminal' => true,
        ]);
    }
}
