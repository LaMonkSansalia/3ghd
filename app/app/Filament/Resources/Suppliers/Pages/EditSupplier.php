<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\WebDiscoveries\WebDiscoveryResource;
use App\Jobs\DiscoverWebsiteJob;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analyzeWebsite')
                ->label('Analizza sito web')
                ->icon('heroicon-o-globe-alt')
                ->color('teal')
                ->visible(fn () => filled($this->record->website))
                ->form([
                    TextInput::make('url')
                        ->label('URL da analizzare')
                        ->url()
                        ->required()
                        ->default(fn () => $this->record->website)
                        ->placeholder('https://www.fornitore.it/prodotti')
                        ->helperText('Pagina catalogo/prodotti — non la homepage generica'),
                ])
                ->action(function (array $data): void {
                    DiscoverWebsiteJob::dispatch($this->record->id, $data['url']);

                    Notification::make()
                        ->title('Analisi avviata')
                        ->body("Analisi di «{$this->record->name}» in coda. Risultati in Analisi Siti tra qualche istante.")
                        ->success()
                        ->send();

                    $this->redirect(WebDiscoveryResource::getUrl('index'));
                }),

            DeleteAction::make(),
        ];
    }
}
