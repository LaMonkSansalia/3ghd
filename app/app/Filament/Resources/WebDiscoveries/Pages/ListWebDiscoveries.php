<?php

namespace App\Filament\Resources\WebDiscoveries\Pages;

use App\Filament\Resources\WebDiscoveries\WebDiscoveryResource;
use Filament\Resources\Pages\ListRecords;

class ListWebDiscoveries extends ListRecords
{
    protected static string $resource = WebDiscoveryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
