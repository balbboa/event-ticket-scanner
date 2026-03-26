<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketScannerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(): Event
    {
        $event = Event::factory()->create(['capacity' => 100, 'status' => 'published']);
        TicketTier::factory()->create(['event_id' => $event->id]);
        return $event;
    }

    #[Test]
    public function it_checks_in_attendee_when_code_passed_as_argument(): void
    {
        $user     = User::factory()->create();
        $event    = $this->makeEvent();
        $tier     = $event->ticketTiers()->first();
        $attendee = Attendee::factory()->create([
            'ticket_tier_id' => $tier->id,
            'status'         => 'confirmed',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\TicketScanner::class, ['event' => $event])
            ->call('scan', $attendee->ticket_code)
            ->assertDispatched('scan-complete')
            ->assertSet('scanResult.status', 'success');

        $this->assertEquals('checked_in', $attendee->fresh()->status);
    }

    #[Test]
    public function it_checks_in_attendee_via_ticketCode_property(): void
    {
        $user     = User::factory()->create();
        $event    = $this->makeEvent();
        $tier     = $event->ticketTiers()->first();
        $attendee = Attendee::factory()->create([
            'ticket_tier_id' => $tier->id,
            'status'         => 'confirmed',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\TicketScanner::class, ['event' => $event])
            ->set('ticketCode', $attendee->ticket_code)
            ->call('scan')
            ->assertDispatched('scan-complete')
            ->assertSet('scanResult.status', 'success');

        $this->assertEquals('checked_in', $attendee->fresh()->status);
    }
}
