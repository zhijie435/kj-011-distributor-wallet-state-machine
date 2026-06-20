<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'wallet_id', 'transaction_no', 'type', 'amount', 'balance_before',
    'balance_after', 'currency', 'reference_type', 'reference_id',
    'operator_id', 'remark', 'metadata',
])]
class WalletTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'type' => WalletTransactionType::class,
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(DealerWallet::class, 'wallet_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeByType($query, WalletTransactionType $type)
    {
        return $query->where('type', $type->value);
    }

    public function scopeIncome($query)
    {
        return $query->whereIn('type', [
            WalletTransactionType::RECHARGE->value,
            WalletTransactionType::REFUND->value,
            WalletTransactionType::TRANSFER_IN->value,
            WalletTransactionType::UNFREEZE->value,
        ]);
    }

    public function scopeExpense($query)
    {
        return $query->whereIn('type', [
            WalletTransactionType::WITHDRAW->value,
            WalletTransactionType::PAYMENT->value,
            WalletTransactionType::TRANSFER_OUT->value,
            WalletTransactionType::FEE->value,
            WalletTransactionType::FREEZE->value,
        ]);
    }
}
