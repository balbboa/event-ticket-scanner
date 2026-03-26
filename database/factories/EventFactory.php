<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
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
            'name'        => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'venue'       => $this->faker->address(),
            'starts_at'   => now()->addDays(7),
            'ends_at'     => now()->addDays(7)->addHours(4),
            'capacity'    => 100,
            'status'      => 'published',
        ];
    }
}
