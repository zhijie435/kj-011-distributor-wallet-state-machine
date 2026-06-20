<?php

namespace App\Enums;

enum WalletTransitionAction: string
{
    case ACTIVATE = 'activate';
    case FREEZE = 'freeze';
    case UNFREEZE = 'unfreeze';
    case RESTRICT = 'restrict';
    case UNRESTRICT = 'unrestrict';
    case FREEZE_FROM_RESTRICTED = 'freeze_from_restricted';
    case CLOSE = 'close';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVATE => '激活',
            self::FREEZE => '冻结',
            self::UNFREEZE => '解冻',
            self::RESTRICT => '限制',
            self::UNRESTRICT => '解除限制',
            self::FREEZE_FROM_RESTRICTED => '受限转冻结',
            self::CLOSE => '注销',
        };
    }

    public function fromStatus(): WalletStatus
    {
        return match ($this) {
            self::ACTIVATE => WalletStatus::INACTIVE,
            self::FREEZE, self::RESTRICT, self::CLOSE => WalletStatus::ACTIVE,
            self::UNFREEZE => WalletStatus::FROZEN,
            self::UNRESTRICT => WalletStatus::RESTRICTED,
            self::FREEZE_FROM_RESTRICTED => WalletStatus::RESTRICTED,
        };
    }

    public function toStatus(): WalletStatus
    {
        return match ($this) {
            self::ACTIVATE => WalletStatus::ACTIVE,
            self::FREEZE, self::FREEZE_FROM_RESTRICTED => WalletStatus::FROZEN,
            self::RESTRICT => WalletStatus::RESTRICTED,
            self::UNFREEZE, self::UNRESTRICT => WalletStatus::ACTIVE,
            self::CLOSE => WalletStatus::CLOSED,
        };
    }
}
