<?php

namespace App\Filament\Resources\Attendees\Tables;

use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->copyable(),
                TextColumn::make('ticket_code')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable(),
                TextColumn::make('ticketTier.event.name')
                    ->label('Event'),
                TextColumn::make('ticketTier.name')
                    ->label('Tier')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'info',
                        'checked_in' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('checked_in_at')
                    ->dateTime()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'checked_in' => 'Checked In',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('event')
                    ->label('Event')
                    ->options(fn () => Event::pluck('name', 'id')->toArray())
                    ->query(fn ($query, $data) => filled($data['value'])
                        ? $query->whereHas('ticketTier', fn ($q) => $q->where('event_id', $data['value']))
                        : $query),
                SelectFilter::make('ticket_tier_id')
                    ->relationship('ticketTier', 'name'),
            ])
            ->recordActions([
                Action::make('check_in')
                    ->visible(fn ($record) => $record->status === 'confirmed')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->checkIn();
                        Notification::make()
                            ->title('Attendee checked in successfully')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
