<?php

namespace App\Filament\Resources\WebDiscoveries\Pages;

use App\Filament\Resources\WebDiscoveries\WebDiscoveryResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWebDiscovery extends ViewRecord
{
    protected static string $resource = WebDiscoveryResource::class;

    protected string $view = 'filament.admin.pages.web-discovery-view';

    public array $selectedItems = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        // Pre-select all non-imported items
        $items = $this->record->items ?? [];
        foreach ($items as $i => $item) {
            if (! ($item['imported'] ?? false)) {
                $this->selectedItems[] = $i;
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_selected')
                ->label('Importa selezionati')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => $this->record->status === 'done' && count($this->selectedItems) > 0)
                ->requiresConfirmation()
                ->modalHeading('Importa prodotti scoperti')
                ->modalDescription(fn () => 'Verranno creati ' . count($this->selectedItems) . ' prodotti per ' . $this->record->supplier->name . '. Potrai modificarli singolarmente dopo.')
                ->action(fn () => $this->importSelected()),

            Action::make('import_all')
                ->label('Importa tutti')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->visible(fn () => $this->record->status === 'done' && count($this->record->items ?? []) > 0)
                ->requiresConfirmation()
                ->action(function () {
                    $items = $this->record->items ?? [];
                    $this->selectedItems = array_keys($items);
                    $this->importSelected();
                }),
        ];
    }

    public function toggleItem(int $index): void
    {
        if (in_array($index, $this->selectedItems)) {
            $this->selectedItems = array_values(array_filter(
                $this->selectedItems,
                fn($i) => $i !== $index
            ));
        } else {
            $this->selectedItems[] = $index;
        }
    }

    public function importSelected(): void
    {
        $items    = $this->record->items ?? [];
        $supplier = $this->record->supplier;
        $created  = 0;
        $skipped  = 0;

        foreach ($this->selectedItems as $idx) {
            $item = $items[$idx] ?? null;
            if (! $item) continue;

            $name = trim($item['name'] ?? '');
            if (empty($name)) { $skipped++; continue; }

            // Skip duplicates: stesso fornitore + stesso nome
            $exists = Product::where('supplier_id', $supplier->id)
                ->where('name', $name)
                ->exists();

            if ($exists) { $skipped++; continue; }

            Product::create([
                'supplier_id'  => $supplier->id,
                'name'         => $name,
                // Campi arricchiti da Claude AI (se disponibili)
                'collection'   => $item['collection'] ?? null,
                'description'  => $item['description'] ?? null,
                'materials'    => $item['materials'] ?? null,
                'finishes'     => $item['finishes'] ?? null,
                'colors'       => $item['colors'] ?? null,
                'source_url'   => $item['url'] ?? $item['source_url'] ?? null,
                // Bozza: senza prezzo, non visibile finché non completato
                'is_active'    => false,
                'is_available' => false,
                'notes'        => 'Importato via analisi web — completare con listino prezzi.',
            ]);
            $created++;

            // Segna come importato nel record WebDiscovery
            $items[$idx]['imported'] = true;
        }

        // Persiste i flag 'imported'
        $this->record->update([
            'items'          => $items,
            'items_imported' => ($this->record->items_imported ?? 0) + $created,
        ]);

        $this->selectedItems = [];

        Notification::make()
            ->title("$created prodotti creati come bozze" . ($skipped > 0 ? " ($skipped saltati — già presenti)" : ''))
            ->body('Vai ai Prodotti per completarli con SKU e listino prezzi.')
            ->success()
            ->send();

        $this->redirect(
            \App\Filament\Resources\Products\ProductResource::getUrl('index')
        );
    }
}
