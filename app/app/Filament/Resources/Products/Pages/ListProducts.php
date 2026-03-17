<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Pages\ImportWizard;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Importa catalogo')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(ImportWizard::getUrl()),

            CreateAction::make(),
        ];
    }
}
