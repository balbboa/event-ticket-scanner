<?php

namespace App\Filament\Resources\ScanLogs;

use App\Filament\Resources\ScanLogs\Pages\ListScanLogs;
use App\Models\Event;
use App\Models\ScanLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScanLogResource extends Resource
{
    protected static ?string $model = ScanLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Scan Logs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scanned_at')
                    ->label('Time')
                    ->dateTime('H:i:s · d/m/Y')
                    ->sortable(),
                TextColumn::make('ticket_code')
                    ->label('Ticket Code')
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                    ->copyable(),
                TextColumn::make('attendee.name')
                    ->label('Attendee')
                    ->default('—'),
                TextColumn::make('event.name')
                    ->label('Event'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success'      => 'success',
                        'already_used' => 'warning',
                        'invalid'      => 'danger',
                        'cancelled'    => 'danger',
                        default        => 'gray',
                    }),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(fn () => Event::pluck('name', 'id')->toArray()),
                SelectFilter::make('status')
                    ->options([
                        'success'      => 'Success',
                        'already_used' => 'Already Used',
                        'invalid'      => 'Invalid',
                        'cancelled'    => 'Cancelled',
                    ]),
            ])
            ->defaultSort('scanned_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScanLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
