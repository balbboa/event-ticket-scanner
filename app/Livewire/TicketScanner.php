<?php

namespace App\Livewire;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\ScanLog;
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
    public array $recentScans = [];

    // Manual lookup
    public string $searchQuery = '';
    public array $searchResults = [];

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

    public function scan(?string $scannedCode = null): void
    {
        if ($scannedCode !== null) {
            $this->ticketCode = $scannedCode;
        }
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
                'ticket_code' => $code,
            ];
        } elseif ($attendee->status === 'checked_in') {
            $this->scanResult = [
                'status' => 'already_used',
                'message' => 'This ticket has already been used.',
                'name' => $attendee->name,
                'tier' => $attendee->ticketTier->name,
                'ticket_code' => $code,
                'checked_in_at' => $attendee->checked_in_at?->format('H:i d/m/Y'),
            ];
        } elseif ($attendee->status === 'cancelled') {
            $this->scanResult = [
                'status' => 'cancelled',
                'message' => 'This ticket has been cancelled.',
                'name' => $attendee->name,
                'tier' => $attendee->ticketTier->name,
                'ticket_code' => $code,
            ];
        } else {
            $attendee->checkIn();
            $this->refreshCounters();
            $this->scanResult = [
                'status' => 'success',
                'message' => 'Welcome! Check-in successful.',
                'name' => $attendee->name,
                'tier' => $attendee->ticketTier->name,
                'ticket_code' => $code,
            ];
        }

        $this->logScan($attendee ?? null, $code, $this->scanResult['status']);
        $this->prependRecentScan($this->scanResult);

        $this->showResult = true;
        $this->dispatch('scan-complete', status: $this->scanResult['status']);
    }

    public function checkInAttendee(int $attendeeId): void
    {
        $attendee = Attendee::whereHas('ticketTier', fn ($q) => $q->where('event_id', $this->event->id))
            ->findOrFail($attendeeId);

        if ($attendee->status !== 'confirmed') {
            return;
        }

        $attendee->checkIn();
        $this->refreshCounters();

        $result = [
            'status' => 'success',
            'message' => 'Welcome! Check-in successful.',
            'name' => $attendee->name,
            'tier' => $attendee->ticketTier->name,
            'ticket_code' => $attendee->ticket_code,
        ];

        $this->logScan($attendee, $attendee->ticket_code, 'success');
        $this->prependRecentScan($result);

        $this->scanResult = $result;
        $this->showResult = true;
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->dispatch('scan-complete', status: 'success');
    }

    public function updatedSearchQuery(): void
    {
        if (strlen($this->searchQuery) < 2) {
            $this->searchResults = [];
            return;
        }

        $term = $this->searchQuery;

        $this->searchResults = Attendee::whereHas('ticketTier', fn ($q) => $q->where('event_id', $this->event->id))
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"))
            ->with('ticketTier')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'email' => $a->email,
                'ticket_code' => $a->ticket_code,
                'tier' => $a->ticketTier->name,
                'status' => $a->status,
            ])
            ->toArray();
    }

    public function dismissResult(): void
    {
        $this->showResult = false;
        $this->scanResult = null;
    }

    private function logScan(?Attendee $attendee, string $code, string $status): void
    {
        ScanLog::create([
            'event_id' => $this->event->id,
            'attendee_id' => $attendee?->id,
            'ticket_code' => $code,
            'status' => $status,
            'ip_address' => request()->ip(),
            'scanned_at' => now(),
        ]);
    }

    private function prependRecentScan(array $result): void
    {
        array_unshift($this->recentScans, [
            'name' => $result['name'] ?? null,
            'ticket_code' => $result['ticket_code'] ?? '',
            'status' => $result['status'],
            'time' => now()->format('H:i:s'),
        ]);

        $this->recentScans = array_slice($this->recentScans, 0, 10);
    }

    public function render()
    {
        return view('livewire.ticket-scanner')
            ->layout('layouts.app', ['title' => 'Scanner — ' . $this->event->name]);
    }
}
