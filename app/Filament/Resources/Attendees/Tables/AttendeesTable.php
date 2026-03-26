<?php

namespace App\Filament\Resources\Attendees\Tables;

use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                Action::make('download_qr')
                    ->label('QR')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->url(fn ($record) => route('attendees.qr', $record))
                    ->openUrlInNewTab(),
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
                    BulkAction::make('export_csv')
                        ->label('Export CSV')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->action(function (Collection $records): StreamedResponse {
                            $headers = [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="attendees-' . now()->format('Y-m-d') . '.csv"',
                            ];

                            $callback = function () use ($records) {
                                $handle = fopen('php://output', 'w');
                                fputcsv($handle, ['Name', 'Email', 'Phone', 'Ticket Code', 'Tier', 'Event', 'Status', 'Checked In At']);
                                foreach ($records->load('ticketTier.event') as $attendee) {
                                    fputcsv($handle, [
                                        $attendee->name,
                                        $attendee->email,
                                        $attendee->phone,
                                        $attendee->ticket_code,
                                        $attendee->ticketTier?->name,
                                        $attendee->ticketTier?->event?->name,
                                        $attendee->status,
                                        $attendee->checked_in_at?->toDateTimeString(),
                                    ]);
                                }
                                fclose($handle);
                            };

                            return response()->stream($callback, 200, $headers);
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
