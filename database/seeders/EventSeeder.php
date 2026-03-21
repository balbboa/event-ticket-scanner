<?php

namespace Database\Seeders;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\TicketTier;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // Event 1
        $event1 = Event::create([
            'name' => 'Laravel & Livewire Summit 2025',
            'description' => 'The premier conference for Laravel and Livewire developers in Brazil.',
            'venue' => 'São Paulo Convention Center, São Paulo',
            'starts_at' => Carbon::now()->addDays(30),
            'ends_at' => Carbon::now()->addDays(30)->addHours(8),
            'capacity' => 300,
            'status' => 'published',
        ]);

        $general = TicketTier::create([
            'event_id' => $event1->id,
            'name' => 'General Admission',
            'description' => 'Standard access to all talks and workshops.',
            'price' => 49.90,
            'quantity' => 200,
        ]);

        $vip = TicketTier::create([
            'event_id' => $event1->id,
            'name' => 'VIP',
            'description' => 'VIP lounge, front-row seating, and exclusive dinner.',
            'price' => 149.90,
            'quantity' => 100,
        ]);

        // 8 checked-in General Admission attendees
        for ($i = 1; $i <= 8; $i++) {
            $a = Attendee::create([
                'ticket_tier_id' => $general->id,
                'name' => "GA Attendee $i",
                'email' => "ga{$i}@example.com",
                'status' => 'confirmed',
            ]);
            $a->checkIn();
        }

        // 12 confirmed General Admission attendees
        for ($i = 9; $i <= 20; $i++) {
            Attendee::create([
                'ticket_tier_id' => $general->id,
                'name' => "GA Attendee $i",
                'email' => "ga{$i}@example.com",
                'status' => 'confirmed',
            ]);
        }

        // 5 confirmed VIP attendees
        for ($i = 1; $i <= 5; $i++) {
            Attendee::create([
                'ticket_tier_id' => $vip->id,
                'name' => "VIP Attendee $i",
                'email' => "vip{$i}@example.com",
                'status' => 'confirmed',
            ]);
        }

        // Event 2
        $event2 = Event::create([
            'name' => 'Filament Admin Workshop',
            'description' => 'Hands-on workshop covering Filament v4 admin panels.',
            'venue' => 'Online — Zoom',
            'starts_at' => Carbon::now()->addDays(7),
            'ends_at' => Carbon::now()->addDays(7)->addHours(4),
            'capacity' => 50,
            'status' => 'published',
        ]);

        $standard = TicketTier::create([
            'event_id' => $event2->id,
            'name' => 'Standard',
            'description' => 'Full access to the online workshop.',
            'price' => 0.00,
            'quantity' => 50,
        ]);

        // 10 confirmed Standard attendees
        for ($i = 1; $i <= 10; $i++) {
            Attendee::create([
                'ticket_tier_id' => $standard->id,
                'name' => "Workshop Attendee $i",
                'email' => "workshop{$i}@example.com",
                'status' => 'confirmed',
            ]);
        }
    }
}
