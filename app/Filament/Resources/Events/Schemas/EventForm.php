<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('venue')
                            ->required(),
                        Textarea::make('description')
                            ->nullable()
                            ->columnSpanFull(),
                        DateTimePicker::make('starts_at')
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->required(),
                        TextInput::make('capacity')
                            ->integer()
                            ->required(),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                        FileUpload::make('cover_image')
                            ->nullable()
                            ->image(),
                    ]),

                Section::make('Ticket Tiers')
                    ->schema([
                        Repeater::make('ticketTiers')
                            ->relationship()
                            ->schema([
                                TextInput::make('name')
                                    ->required(),
                                Textarea::make('description')
                                    ->nullable(),
                                TextInput::make('price')
                                    ->numeric()
                                    ->prefix('$'),
                                TextInput::make('quantity')
                                    ->integer(),
                            ]),
                    ]),
            ]);
    }
}
