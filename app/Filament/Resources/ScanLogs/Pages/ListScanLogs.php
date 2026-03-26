<?php

namespace App\Filament\Resources\ScanLogs\Pages;

use App\Filament\Resources\ScanLogs\ScanLogResource;
use Filament\Resources\Pages\ListRecords;

class ListScanLogs extends ListRecords
{
    protected static string $resource = ScanLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
