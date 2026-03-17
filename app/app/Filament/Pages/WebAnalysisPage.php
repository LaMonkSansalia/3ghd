<?php

namespace App\Filament\Pages;

use App\Jobs\DiscoverWebsiteJob;
use App\Models\Supplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Trigger page: seleziona fornitore + URL → dispatcha DiscoverWebsiteJob.
 * La review e l'import avvengono in WebDiscoveryResource (ViewWebDiscovery).
 */
class WebAnalysisPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.web-analysis-page';

    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Analisi Sito Fornitore';
    protected static \UnitEnum|string|null $navigationGroup = 'Catalogo';
    protected static ?int    $navigationSort  = 11;
    protected static ?string $title           = 'Analisi Sito Web Fornitore';

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('formData')
            ->components([
                Select::make('supplier_id')
                    ->label('Fornitore')
                    ->options(Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $website = Supplier::find($state)?->website;
                            if ($website) {
                                $set('url', $website);
                            }
                        }
                    }),

                TextInput::make('url')
                    ->label('URL da analizzare')
                    ->url()
                    ->required()
                    ->placeholder('https://www.fornitore.it/prodotti')
                    ->helperText('Inserisci la pagina catalogo/prodotti — non la homepage generica'),
            ]);
    }

    public function startAnalysis(): void
    {
        $data = $this->form->getState();

        DiscoverWebsiteJob::dispatch($data['supplier_id'], $data['url']);

        Notification::make()
            ->title('Analisi avviata')
            ->body('Il job è in coda. Troverai i risultati in Analisi Siti tra qualche istante.')
            ->success()
            ->send();

        $this->redirect(
            \App\Filament\Resources\WebDiscoveries\WebDiscoveryResource::getUrl('index')
        );
    }
}
