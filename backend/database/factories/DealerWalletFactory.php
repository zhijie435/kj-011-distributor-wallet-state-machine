<?php

namespace Database\Factories;

use App\Enums\WalletStatus;
use App\Models\Distributor;
use Illuminate\Database\Eloquent\Factories\Factory;

class DealerWalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'distributor_id' => Distributor::factory(),
            'wallet_no' => 'W' . now()->format('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'status' => WalletStatus::INACTIVE->value,
            'balance' => 0,
            'frozen_amount' => 0,
            'credit_limit' => fake()->randomFloat(2, 1000, 100000),
            'currency' => 'CNY',
        ];
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletStatus::ACTIVE->value,
            'last_activated_at' => now(),
        ]);
    }

    public function frozen(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletStatus::FROZEN->value,
            'last_frozen_at' => now(),
            'freeze_reason' => '测试冻结原因',
        ]);
    }

    public function restricted(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletStatus::RESTRICTED->value,
            'last_restricted_at' => now(),
            'restrict_reason' => '测试限制原因',
        ]);
    }

    public function closed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletStatus::CLOSED->value,
            'closed_at' => now(),
            'close_reason' => '测试注销原因',
        ]);
    }
}
