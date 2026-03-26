<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'name', 'description', 'price', 'quantity',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function getSoldCountAttribute(): int
    {
        return $this->attendees()->whereIn('status', ['confirmed', 'checked_in'])->count();
    }

    public function getRemainingAttribute(): int
    {
        return max(0, $this->quantity - $this->sold_count);
    }
}
