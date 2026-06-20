<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case RECHARGE = 'recharge';
    case WITHDRAW = 'withdraw';
    case PAYMENT = 'payment';
    case REFUND = 'refund';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case FEE = 'fee';
    case ADJUSTMENT = 'adjustment';
    case FREEZE = 'freeze';
    case UNFREEZE = 'unfreeze';

    public function label(): string
    {
        return match ($this) {
            self::RECHARGE => '充值',
            self::WITHDRAW => '提现',
            self::PAYMENT => '消费',
            self::REFUND => '退款',
            self::TRANSFER_IN => '转入',
            self::TRANSFER_OUT => '转出',
            self::FEE => '手续费',
            self::ADJUSTMENT => '调整',
            self::FREEZE => '冻结',
            self::UNFREEZE => '解冻',
        };
    }

    public function isIncome(): bool
    {
        return in_array($this, [self::RECHARGE, self::REFUND, self::TRANSFER_IN, self::UNFREEZE], true);
    }

    public function isExpense(): bool
    {
        return in_array($this, [self::WITHDRAW, self::PAYMENT, self::TRANSFER_OUT, self::FEE, self::FREEZE], true);
    }

    public function affectsBalance(): bool
    {
        return !in_array($this, [self::FREEZE, self::UNFREEZE], true);
    }
}
