<?php

namespace App\Repositories;

use App\Enums\WalletStatus;
use App\Enums\WalletTransactionType;
use App\Models\DealerWallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class WalletRepository extends BaseRepository
{
    public function __construct(DealerWallet $model)
    {
        parent::__construct($model);
    }

    public function findByDistributorId(int $distributorId): ?DealerWallet
    {
        return $this->model->where('distributor_id', $distributorId)->first();
    }

    public function findByWalletNo(string $walletNo): ?DealerWallet
    {
        return $this->model->where('wallet_no', $walletNo)->first();
    }

    public function lockById(int $id): DealerWallet
    {
        return $this->model->lockForUpdate()->findOrFail($id);
    }

    public function getByStatus(WalletStatus $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('status', $status->value)
            ->with(['distributor'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function countByStatus(): array
    {
        return $this->model
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function list(array $params = [], array $with = ['distributor']): LengthAwarePaginator
    {
        $query = $this->model->with($with);

        $this->applyListFilters($query, $params);

        return $query->orderBy('id', 'desc')
            ->paginate($params['per_page'] ?? 20);
    }

    protected function applyListFilters(Builder $query, array $params): void
    {
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (isset($params['statuses']) && is_array($params['statuses'])) {
            $query->whereIn('status', $params['statuses']);
        }

        if (isset($params['distributor_id'])) {
            $query->where('distributor_id', $params['distributor_id']);
        }

        if (isset($params['wallet_no'])) {
            $query->where('wallet_no', 'like', "%{$params['wallet_no']}%");
        }

        if (isset($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('distributor', function (Builder $subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                })->orWhere('wallet_no', 'like', "%{$search}%");
            });
        }

        if (isset($params['min_balance'])) {
            $query->where('balance', '>=', $params['min_balance']);
        }

        if (isset($params['max_balance'])) {
            $query->where('balance', '<=', $params['max_balance']);
        }
    }

    public function getTransactions(
        DealerWallet $wallet,
        array $params = [],
        array $with = ['operator']
    ): LengthAwarePaginator {
        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->with($with);

        $this->applyTransactionFilters($query, $params);

        return $query->orderBy('id', 'desc')
            ->paginate($params['per_page'] ?? 20);
    }

    public function getStateLogs(
        DealerWallet $wallet,
        array $params = [],
        array $with = ['operator']
    ): LengthAwarePaginator {
        $query = $wallet->stateLogs()->with($with);

        if (isset($params['action'])) {
            $query->where('action', $params['action']);
        }

        if (isset($params['operator_id'])) {
            $query->where('operator_id', $params['operator_id']);
        }

        return $query->orderBy('id', 'desc')
            ->paginate($params['per_page'] ?? 20);
    }

    public function getStatistics(DealerWallet $wallet, array $params = []): array
    {
        $startDate = $params['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $params['end_date'] ?? now()->endOfMonth()->toDateString();

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

        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->whereBetween('created_at', [$startDate, $endDate]);

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

        $income = (float) ($stats->income ?? 0);
        $expense = (float) ($stats->expense ?? 0);

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'income' => $income,
            'expense' => $expense,
            'net_flow' => bcsub((string) $income, (string) $expense, 2),
            'transaction_count' => (int) ($stats->total_count ?? 0),
        ];
    }

    protected function applyTransactionFilters(Builder $query, array $params): void
    {
        if (isset($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (isset($params['types']) && is_array($params['types'])) {
            $query->whereIn('type', $params['types']);
        }

        if (isset($params['start_date'])) {
            $query->whereDate('created_at', '>=', $params['start_date']);
        }

        if (isset($params['end_date'])) {
            $query->whereDate('created_at', '<=', $params['end_date']);
        }

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
}
