<?php

namespace Database\Factories;

use App\Models\Attendee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendee>
 */
class AttendeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_tier_id' => \App\Models\TicketTier::factory(),
            'name'           => $this->faker->name(),
            'email'          => $this->faker->unique()->safeEmail(),
            'phone'          => $this->faker->phoneNumber(),
            'status'         => 'confirmed',
        ];
    }
}
