<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Event extends Model
{
    protected $fillable = [
        'name', 'description', 'venue', 'starts_at', 'ends_at',
        'capacity', 'status', 'cover_image',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function ticketTiers(): HasMany
    {
        return $this->hasMany(TicketTier::class);
    }

    public function attendees(): HasManyThrough
    {
        return $this->hasManyThrough(Attendee::class, TicketTier::class);
    }

    public function getTotalCheckedInAttribute(): int
    {
        return $this->attendees()->where('status', 'checked_in')->count();
    }

    public function getCapacityFilledPercentAttribute(): float
    {
        if ($this->capacity === 0) return 0;
        return round(($this->attendees()->count() / $this->capacity) * 100, 1);
    }
}
