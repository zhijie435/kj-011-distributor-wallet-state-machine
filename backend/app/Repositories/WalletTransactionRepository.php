<?php

namespace App\Repositories;

use App\Enums\WalletTransactionType;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class WalletTransactionRepository extends BaseRepository
{
    public function __construct(WalletTransaction $model)
    {
        parent::__construct($model);
    }

    public function getByWalletId(
        int $walletId,
        int $perPage = 20,
        array $with = ['operator']
    ): LengthAwarePaginator {
        return $this->model->where('wallet_id', $walletId)
            ->with($with)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function getByType(
        WalletTransactionType $type,
        int $perPage = 20,
        array $with = ['wallet.distributor', 'operator']
    ): LengthAwarePaginator {
        return $this->model->where('type', $type->value)
            ->with($with)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function filterByWallet(
        int $walletId,
        array $params = [],
        array $with = ['operator']
    ): LengthAwarePaginator {
        $query = $this->model->where('wallet_id', $walletId)->with($with);

        $this->applyFilters($query, $params);

        return $query->orderBy('id', 'desc')
            ->paginate($params['per_page'] ?? 20);
    }

    public function findByTransactionNo(string $transactionNo): ?WalletTransaction
    {
        return $this->model->where('transaction_no', $transactionNo)->first();
    }

    public function findByReference(string $referenceType, int $referenceId): ?WalletTransaction
    {
        return $this->model->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->first();
    }

    public function create(array $data): WalletTransaction
    {
        return $this->model->create($data);
    }

    public function getIncomeSum(int $walletId, ?string $startDate = null, ?string $endDate = null): float
    {
        $query = $this->model->where('wallet_id', $walletId)
            ->whereIn('type', [
                WalletTransactionType::RECHARGE->value,
                WalletTransactionType::REFUND->value,
                WalletTransactionType::TRANSFER_IN->value,
            ]);

        $this->applyDateRange($query, $startDate, $endDate);

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

        $this->applyDateRange($query, $startDate, $endDate);

        return abs((float) $query->sum('amount'));
    }

    public function getSumByType(int $walletId, WalletTransactionType $type, ?string $startDate = null, ?string $endDate = null): float
    {
        $query = $this->model->where('wallet_id', $walletId)
            ->where('type', $type->value);

        $this->applyDateRange($query, $startDate, $endDate);

        return (float) $query->sum('amount');
    }

    public function getAggregateStats(int $walletId, ?string $startDate = null, ?string $endDate = null): array
    {
        $incomeTypes = [
            WalletTransactionType::RECHARGE->value,
            WalletTransactionType::REFUND->value,
            WalletTransactionType::TRANSFER_IN->value,
        ];

        $expenseTypes = [
            WalletTransactionType::WITHDRAW->value,
            WalletTransactionType::PAYMENT->value,
            WalletTransactionType::TRANSFER_OUT->value,
            WalletTransactionType::FEE->value,
        ];

        $query = $this->model->where('wallet_id', $walletId);
        $this->applyDateRange($query, $startDate, $endDate);

        $incomePlaceholders = implode(',', array_fill(0, count($incomeTypes), '?'));
        $expensePlaceholders = implode(',', array_fill(0, count($expenseTypes), '?'));
        $bindings = array_merge($incomeTypes, $expenseTypes);

        $stats = (clone $query)
            ->selectRaw("
                SUM(CASE WHEN type IN ($incomePlaceholders) THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type IN ($expensePlaceholders) THEN ABS(amount) ELSE 0 END) as expense,
                COUNT(*) as total_count
            ", $bindings)
            ->first();

        return [
            'income' => (float) ($stats->income ?? 0),
            'expense' => (float) ($stats->expense ?? 0),
            'net_flow' => bcsub((string) ($stats->income ?? 0), (string) ($stats->expense ?? 0), 2),
            'transaction_count' => (int) ($stats->total_count ?? 0),
        ];
    }

    protected function applyFilters(Builder $query, array $params): void
    {
        if (isset($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (isset($params['types']) && is_array($params['types'])) {
            $query->whereIn('type', $params['types']);
        }

        $this->applyDateRange($query, $params['start_date'] ?? null, $params['end_date'] ?? null);

        if (isset($params['min_amount'])) {
            $query->whereRaw('ABS(amount) >= ?', [$params['min_amount']]);
        }

        if (isset($params['max_amount'])) {
            $query->whereRaw('ABS(amount) <= ?', [$params['max_amount']]);
        }

        if (isset($params['reference_type'])) {
            $query->where('reference_type', $params['reference_type']);
        }

        if (isset($params['reference_id'])) {
            $query->where('reference_id', $params['reference_id']);
        }
    }

    protected function applyDateRange(Builder $query, ?string $startDate, ?string $endDate): void
    {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
    }
}
