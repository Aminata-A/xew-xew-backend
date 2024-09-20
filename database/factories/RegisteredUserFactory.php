<?php

namespace Database\Factories;

use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RegisteredUser>
 */
class RegisteredUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected static ?string $password;


    public function definition(): array
    {
        return [
            'user_id' => $this->faker->numberBetween(1, 10),
            'balance' => $this->faker->numberBetween(1, 10000),
            'password' => static::$password ??= Hash::make('password'),
            'role' => $this->faker->randomElement(['organizer', 'participant']),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'photo' => $this->faker->word(),
            'created_at' => now(),
            'updated_at' => now(),

        ];
    }
}
