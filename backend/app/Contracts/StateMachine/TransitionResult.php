<?php

namespace App\Contracts\StateMachine;

class TransitionResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly string $message = '',
        public readonly array $errors = [],
    ) {
    }

    public static function success(string $message = ''): self
    {
        return new self(true, $message);
    }

    public static function failure(string $message, array $errors = []): self
    {
        return new self(false, $message, $errors);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function isInvalid(): bool
    {
        return !$this->valid;
    }
}
