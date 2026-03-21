<?php

namespace App\Livewire;

use App\Models\Attendee;
use App\Models\Event;
use Livewire\Component;

class TicketScanner extends Component
{
    public Event $event;
    public string $ticketCode = '';
    public ?array $scanResult = null;
    public bool $showResult = false;
    public int $totalCapacity = 0;
    public int $checkedIn = 0;
    public int $confirmed = 0;
    public int $cancelled = 0;

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->totalCapacity = $event->capacity;
        $this->refreshCounters();
    }

    public function refreshCounters(): void
    {
        $counts = $this->event->attendees()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->checkedIn = $counts['checked_in'] ?? 0;
        $this->confirmed = $counts['confirmed'] ?? 0;
        $this->cancelled = $counts['cancelled'] ?? 0;
    }

    public function scan(): void
    {
        $code = strtoupper(trim($this->ticketCode));
        $this->ticketCode = '';

        $attendee = Attendee::where('ticket_code', $code)
            ->whereHas('ticketTier', fn ($q) => $q->where('event_id', $this->event->id))
            ->first();

        if (! $attendee) {
            $this->scanResult = [
                'status' => 'invalid',
                'message' => 'Ticket not found.',
                'name' => null,
                'tier' => null,
            ];
        } elseif ($attendee->status === 'checked_in') {
            $this->scanResult = [
                'status' => 'already_used',
                'message' => 'This ticket has already been used.',
                'name' => $attendee->name,
                'tier' => $attendee->ticketTier->name,
                'checked_in_at' => $attendee->checked_in_at?->format('H:i d/m/Y'),
            ];
        } elseif ($attendee->status === 'cancelled') {
            $this->scanResult = [
                'status' => 'cancelled',
                'message' => 'This ticket has been cancelled.',
                'name' => $attendee->name,
                'tier' => $attendee->ticketTier->name,
            ];
        } else {
            $attendee->checkIn();
            $this->refreshCounters();
            $this->scanResult = [
                'status' => 'success',
                'message' => 'Welcome! Check-in successful.',
                'name' => $attendee->name,
                'tier' => $attendee->ticketTier->name,
            ];
        }

        $this->showResult = true;
        $this->dispatch('scan-complete', status: $this->scanResult['status']);
    }

    public function dismissResult(): void
    {
        $this->showResult = false;
        $this->scanResult = null;
    }

    public function render()
    {
        return view('livewire.ticket-scanner')
            ->layout('layouts.app', ['title' => 'Scanner — ' . $this->event->name]);
    }
}
