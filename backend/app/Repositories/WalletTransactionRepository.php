<?php

namespace App\Repositories;

use App\Enums\WalletTransactionType;
use App\Models\WalletTransaction;

class WalletTransactionRepository extends BaseRepository
{
    public function __construct(WalletTransaction $model)
    {
        parent::__construct($model);
    }

    public function getByWalletId(int $walletId, int $perPage = 20)
    {
        return $this->model->where('wallet_id', $walletId)
            ->with(['operator'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function getByType(WalletTransactionType $type, int $perPage = 20)
    {
        return $this->model->where('type', $type->value)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function getIncomeSum(int $walletId, ?string $startDate = null, ?string $endDate = null): float
    {
        $query = $this->model->where('wallet_id', $walletId)
            ->whereIn('type', [
                WalletTransactionType::RECHARGE->value,
                WalletTransactionType::REFUND->value,
                WalletTransactionType::TRANSFER_IN->value,
            ]);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return (float) $query->sum('amount');
    }

    public function getExpenseSum(int $walletId, ?string $startDate = null, ?string $endDate = null): float
    {
        $query = $this->model->where('wallet_id', $walletId)
            ->whereIn('type', [
                WalletTransactionType::WITHDRAW->value,
                WalletTransactionType::PAYMENT->value,
                WalletTransactionType::TRANSFER_OUT->value,
                WalletTransactionType::FEE->value,
            ]);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return abs((float) $query->sum('amount'));
    }
}
