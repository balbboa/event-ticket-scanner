<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Attendee extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_tier_id', 'name', 'email', 'phone', 'ticket_code',
        'status', 'checked_in_at', 'discount_code',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    public function ticketTier(): BelongsTo
    {
        return $this->belongsTo(TicketTier::class);
    }

    public function getEventAttribute(): Event
    {
        return $this->ticketTier->event;
    }

    protected static function booted(): void
    {
        static::creating(function (Attendee $attendee) {
            if (empty($attendee->ticket_code)) {
                $attendee->ticket_code = static::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = 'EVT-' . strtoupper(Str::random(8));
        } while (static::where('ticket_code', $code)->exists());

        return $code;
    }

    public function checkIn(): bool
    {
        if ($this->status !== 'confirmed') {
            return false;
        }

        $this->status = 'checked_in';
        $this->checked_in_at = now();
        $this->save();

        return true;
    }
}
