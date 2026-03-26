<?php

namespace Database\Factories;

use App\Models\TicketTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketTier>
 */
class TicketTierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id'    => \App\Models\Event::factory(),
            'name'        => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'price'       => $this->faker->randomFloat(2, 0, 200),
            'quantity'    => 50,
        ];
    }
}
