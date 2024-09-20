<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'balance' => $this->faker->numberBetween(1, 10000),
            'user_id' => $this->faker->numberBetween(1, 10),
            'name' => $this->faker->name,
            'identifier' => $this->faker->unique()->word,
            'phone_number' => $this->faker->phoneNumber
        ];
    }
}
