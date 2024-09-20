<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => $this->faker->numberBetween(1, 10),
            'ticket_id' => $this->faker->numberBetween(1, 10),
            'amount' => $this->faker->numberBetween(100, 1000),
            'type' => $this->faker->randomElement(['debit', 'achat']),
            'wallet_id' => $this->faker->numberBetween(1, 10)
        ];
    }
}
