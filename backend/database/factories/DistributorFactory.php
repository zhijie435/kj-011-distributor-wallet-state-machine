<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DistributorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'company_name' => fake()->company(),
            'type' => fake()->randomElement(['first_level', 'second_level']),
            'region' => fake()->city(),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'bank_name' => fake()->company(),
            'bank_account' => fake()->numerify('##################'),
            'credit_limit' => fake()->randomFloat(2, 1000, 100000),
            'balance' => 0,
            'status' => 'active',
        ];
    }
}
