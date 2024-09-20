<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'category_id' => $this->faker->numberBetween(1, 10),
            'date' => $this->faker->date(),
            'time' => $this->faker->time(),
            'location' => $this->faker->word(),
            'event_status' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'banner' => $this->faker->word(),
            'ticket_quantity' => $this->faker->numberBetween(1, 10),
            'ticket_price' => $this->faker->numberBetween(1, 10),
            'organizer_id' => $this->faker->numberBetween(1, 10),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
