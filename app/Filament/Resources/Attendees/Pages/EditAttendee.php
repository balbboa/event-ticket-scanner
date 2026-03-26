<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendee extends EditRecord
{
    protected static string $resource = AttendeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_qr')
                ->label('Download QR')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedQrCode)
                ->url(fn () => route('attendees.qr', $this->record))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
