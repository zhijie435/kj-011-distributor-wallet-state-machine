<?php

namespace App\Enums;

enum UserType: string
{
    case PLATFORM = 'platform';
    case DISTRIBUTOR = 'distributor';

    public function label(): string
    {
        return match ($this) {
            self::PLATFORM => '平台',
            self::DISTRIBUTOR => '经销商',
        };
    }
}
