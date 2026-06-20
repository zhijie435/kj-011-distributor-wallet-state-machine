<?php

namespace App\Contracts\StateMachine;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;

interface StateMachineInterface
{
    public function getModel(): Model;

    public function currentState(): BackedEnum;

    public function canTransitionTo(BackedEnum $targetState, array $context = []): bool;

    public function transitionTo(BackedEnum $targetState, array $context = []): Model;

    public function allowedTransitions(): array;

    public function validateTransition(BackedEnum $targetState, array $context = []): TransitionResult;
}
