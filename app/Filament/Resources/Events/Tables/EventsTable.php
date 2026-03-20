<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('venue'),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('capacity'),
                TextColumn::make('total_checked_in')
                    ->label('Checked In')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->total_checked_in)
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'cancelled' => 'Cancelled',
                    ]),
                Filter::make('upcoming')
                    ->query(fn (Builder $query) => $query->whereDate('starts_at', '>=', now())),
                Filter::make('this_month')
                    ->query(fn (Builder $query) => $query->whereBetween('starts_at', [
                        now()->startOfMonth(),
                        now()->endOfMonth(),
                    ])),
            ])
            ->recordActions([
                Action::make('open_scanner')
                    ->url(fn ($record) => route('scanner', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}
