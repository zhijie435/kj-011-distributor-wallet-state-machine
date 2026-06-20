<?php

namespace App\Enums;

enum WalletStatus: string
{
    case INACTIVE = 'inactive';
    case ACTIVE = 'active';
    case FROZEN = 'frozen';
    case RESTRICTED = 'restricted';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::INACTIVE => '未激活',
            self::ACTIVE => '正常',
            self::FROZEN => '已冻结',
            self::RESTRICTED => '受限',
            self::CLOSED => '已注销',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INACTIVE => 'info',
            self::ACTIVE => 'success',
            self::FROZEN => 'danger',
            self::RESTRICTED => 'warning',
            self::CLOSED => '',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::CLOSED;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isFrozen(): bool
    {
        return $this === self::FROZEN;
    }

    public function isRestricted(): bool
    {
        return $this === self::RESTRICTED;
    }

    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }

    public function canTransitionTo(self $target): bool
    {
        $transitions = [
            self::INACTIVE->value => [
                self::ACTIVE->value,
                self::CLOSED->value,
            ],
            self::ACTIVE->value => [
                self::FROZEN->value,
                self::RESTRICTED->value,
                self::CLOSED->value,
            ],
            self::FROZEN->value => [
                self::ACTIVE->value,
                self::CLOSED->value,
            ],
            self::RESTRICTED->value => [
                self::ACTIVE->value,
                self::FROZEN->value,
                self::CLOSED->value,
            ],
            self::CLOSED->value => [],
        ];

        return in_array($target->value, $transitions[$this->value] ?? [], true);
    }

    public function allowedTransitions(): array
    {
        $transitions = [
            self::INACTIVE->value => [self::ACTIVE, self::CLOSED],
            self::ACTIVE->value => [self::FROZEN, self::RESTRICTED, self::CLOSED],
            self::FROZEN->value => [self::ACTIVE, self::CLOSED],
            self::RESTRICTED->value => [self::ACTIVE, self::FROZEN, self::CLOSED],
            self::CLOSED->value => [],
        ];

        return $transitions[$this->value] ?? [];
    }
}
