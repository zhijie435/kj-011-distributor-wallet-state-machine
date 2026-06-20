<?php

namespace App\Policies;

use App\Models\DealerWallet;
use App\Models\User;

class DealerWalletPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatform() || $user->isDistributor();
    }

    public function view(User $user, DealerWallet $wallet): bool
    {
        if ($user->isPlatform()) {
            return true;
        }

        return $user->distributor_id === $wallet->distributor_id;
    }

    public function create(User $user): bool
    {
        return $user->isPlatform();
    }

    public function activate(User $user, DealerWallet $wallet): bool
    {
        return $user->isPlatform();
    }

    public function freeze(User $user, DealerWallet $wallet): bool
    {
        return $user->isPlatform();
    }

    public function unfreeze(User $user, DealerWallet $wallet): bool
    {
        return $user->isPlatform();
    }

    public function restrict(User $user, DealerWallet $wallet): bool
    {
        return $user->isPlatform();
    }

    public function unrestrict(User $user, DealerWallet $wallet): bool
    {
        return $user->isPlatform();
    }

    public function close(User $user, DealerWallet $wallet): bool
    {
        return $user->isPlatform();
    }

    public function recharge(User $user, DealerWallet $wallet): bool
    {
        return $user->isPlatform();
    }
}
