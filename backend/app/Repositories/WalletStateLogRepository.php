<?php

namespace App\Repositories;

use App\Enums\WalletTransitionAction;
use App\Models\WalletStateLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WalletStateLogRepository extends BaseRepository
{
    public function __construct(WalletStateLog $model)
    {
        parent::__construct($model);
    }

    public function getByWalletId(int $walletId, int $perPage = 20, array $with = ['operator']): LengthAwarePaginator
    {
        return $this->model->where('wallet_id', $walletId)
            ->with($with)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function getByAction(WalletTransitionAction $action, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->where('action', $action->value)
            ->with(['wallet', 'operator'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function getByOperator(int $operatorId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->where('operator_id', $operatorId)
            ->with(['wallet.distributor'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function create(array $data): WalletStateLog
    {
        return $this->model->create($data);
    }

    public function countByWalletId(int $walletId): int
    {
        return $this->model->where('wallet_id', $walletId)->count();
    }
}
