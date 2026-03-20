<?php

namespace App\Filament\Resources\Attendees\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AttendeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('ticket_tier_id')
                    ->relationship('ticketTier', 'name')
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->event->name} — {$record->name}")
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->nullable(),
                Select::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'checked_in' => 'Checked In',
                        'cancelled' => 'Cancelled',
                    ]),
                DateTimePicker::make('checked_in_at')
                    ->disabled(),
            ]);
    }
}
