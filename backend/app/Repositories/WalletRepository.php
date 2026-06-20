<?php

namespace App\Repositories;

use App\Enums\WalletStatus;
use App\Models\DealerWallet;

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

    public function getByStatus(WalletStatus $status, int $perPage = 15)
    {
        return $this->model->where('status', $status->value)
            ->with(['distributor'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function countByStatus(): array
    {
        $counts = [];
        foreach (WalletStatus::cases() as $status) {
            $counts[$status->value] = $this->model->where('status', $status->value)->count();
        }
        return $counts;
    }
}
