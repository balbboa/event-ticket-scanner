<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EventStatsWidget extends StatsOverviewWidget
{
    public ?Event $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $event = $this->record;

        $counts = $event->attendees()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $checkedIn  = $counts['checked_in'] ?? 0;
        $confirmed  = $counts['confirmed'] ?? 0;
        $cancelled  = $counts['cancelled'] ?? 0;
        $capacity   = $event->capacity;
        $fillPct    = $capacity > 0 ? round($checkedIn / $capacity * 100, 1) : 0;

        return [
            Stat::make('Checked In', $checkedIn)
                ->description("{$fillPct}% of {$capacity} capacity")
                ->descriptionIcon(\Filament\Support\Icons\Heroicon::OutlinedCheckCircle)
                ->color('success'),

            Stat::make('Confirmed', $confirmed)
                ->description('Awaiting check-in')
                ->descriptionIcon(\Filament\Support\Icons\Heroicon::OutlinedClock)
                ->color('info'),

            Stat::make('Cancelled', $cancelled)
                ->description('Cancelled tickets')
                ->descriptionIcon(\Filament\Support\Icons\Heroicon::OutlinedXCircle)
                ->color('danger'),
        ];
    }
}
