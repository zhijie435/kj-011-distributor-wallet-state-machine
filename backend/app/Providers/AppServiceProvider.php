<?php

namespace App\Providers;

use App\Models\DealerWallet;
use App\Observers\DealerWalletObserver;
use App\Policies\DealerWalletPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerRepositories();
    }

    public function boot(): void
    {
        DealerWallet::observe(DealerWalletObserver::class);

        Gate::policy(DealerWallet::class, DealerWalletPolicy::class);
    }

    protected function registerRepositories(): void
    {
        $this->app->singleton(
            \App\Contracts\RepositoryInterface::class . '@Wallet',
            \App\Repositories\WalletRepository::class
        );

        $this->app->singleton(
            \App\Contracts\RepositoryInterface::class . '@WalletTransaction',
            \App\Repositories\WalletTransactionRepository::class
        );
    }
}
