<?php

namespace App\Policies;

use App\Enums\WalletStatus;
use App\Models\DealerWallet;
use App\Models\User;

class DealerWalletPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isPlatform() && $this->isPlatformAdminOperation($ability)) {
            return null;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isPlatform() || $user->isDistributor();
    }

    public function view(User $user, DealerWallet $wallet): bool
    {
        if ($user->isPlatform()) {
            return true;
        }

        return $user->isDistributor()
            && $user->distributor_id === $wallet->distributor_id;
    }

    public function create(User $user): bool
    {
        return $user->isPlatform();
    }

    public function activate(User $user, DealerWallet $wallet): bool
    {
        if (!$user->isPlatform()) {
            return false;
        }

        return $wallet->status === WalletStatus::INACTIVE;
    }

    public function freeze(User $user, DealerWallet $wallet): bool
    {
        if (!$user->isPlatform()) {
            return false;
        }

        return in_array($wallet->status, [
            WalletStatus::ACTIVE,
            WalletStatus::RESTRICTED,
        ], true);
    }

    public function unfreeze(User $user, DealerWallet $wallet): bool
    {
        if (!$user->isPlatform()) {
            return false;
        }

        return $wallet->status === WalletStatus::FROZEN;
    }

    public function restrict(User $user, DealerWallet $wallet): bool
    {
        if (!$user->isPlatform()) {
            return false;
        }

        return $wallet->status === WalletStatus::ACTIVE;
    }

    public function unrestrict(User $user, DealerWallet $wallet): bool
    {
        if (!$user->isPlatform()) {
            return false;
        }

        return $wallet->status === WalletStatus::RESTRICTED;
    }

    public function close(User $user, DealerWallet $wallet): bool
    {
        if (!$user->isPlatform()) {
            return false;
        }

        if ($wallet->status === WalletStatus::CLOSED) {
            return false;
        }

        return (float) $wallet->balance === 0.0
            && (float) $wallet->frozen_amount === 0.0;
    }

    public function recharge(User $user, DealerWallet $wallet): bool
    {
        if (!$user->isPlatform()) {
            return false;
        }

        return $wallet->isActive();
    }

    public function deduct(User $user, DealerWallet $wallet): bool
    {
        if ($user->isPlatform()) {
            return $wallet->isActive();
        }

        if ($user->isDistributor() && $user->distributor_id === $wallet->distributor_id) {
            return $wallet->isActive();
        }

        return false;
    }

    public function freezeAmount(User $user, DealerWallet $wallet): bool
    {
        if (!$user->isPlatform()) {
            return false;
        }

        return $wallet->isActive();
    }

    public function unfreezeAmount(User $user, DealerWallet $wallet): bool
    {
        if (!$user->isPlatform()) {
            return false;
        }

        return !$wallet->isClosed()
            && (float) $wallet->frozen_amount > 0;
    }

    public function withdraw(User $user, DealerWallet $wallet): bool
    {
        if ($user->isPlatform()) {
            return $wallet->isActive();
        }

        if ($user->isDistributor() && $user->distributor_id === $wallet->distributor_id) {
            return $wallet->isActive();
        }

        return false;
    }

    public function viewTransactions(User $user, DealerWallet $wallet): bool
    {
        return $this->view($user, $wallet);
    }

    public function viewStateLogs(User $user, DealerWallet $wallet): bool
    {
        if ($user->isPlatform()) {
            return true;
        }

        return false;
    }

    public function viewStatistics(User $user, DealerWallet $wallet): bool
    {
        return $this->view($user, $wallet);
    }

    public function viewMyWallet(User $user): bool
    {
        return $user->isDistributor();
    }

    protected function isPlatformAdminOperation(string $ability): bool
    {
        return in_array($ability, [
            'create',
            'activate',
            'freeze',
            'unfreeze',
            'restrict',
            'unrestrict',
            'close',
            'recharge',
            'freezeAmount',
            'unfreezeAmount',
            'viewStateLogs',
        ], true);
    }
}
