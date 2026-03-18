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

    public array $editableItems = [];
    public array $selectedItems = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $items = $this->record->items ?? [];
        foreach ($items as $i => $item) {
            $this->editableItems[$i] = $item;
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
        ];
    }

    public function toggleItem(int $index): void
    {
        if (in_array($index, $this->selectedItems)) {
            $this->selectedItems = array_values(array_filter(
                $this->selectedItems,
                fn ($i) => $i !== $index
            ));
        } else {
            $this->selectedItems[] = $index;
        }
    }

    public function toggleAll(): void
    {
        $nonImported = array_keys(array_filter(
            $this->editableItems,
            fn ($item) => ! ($item['imported'] ?? false)
        ));

        if (count($this->selectedItems) === count($nonImported)) {
            $this->selectedItems = [];
        } else {
            $this->selectedItems = array_values($nonImported);
        }
    }

    public function importSelected(): void
    {
        $supplier = $this->record->supplier;
        $created  = 0;
        $skipped  = 0;

        foreach ($this->selectedItems as $idx) {
            $item = $this->editableItems[$idx] ?? null;
            if (! $item) {
                continue;
            }

            $name = trim($item['name'] ?? '');
            if (empty($name)) {
                $skipped++;
                continue;
            }

            $sku = $item['sku'] ?? null;
            $query = Product::where('supplier_id', $supplier->id);
            if ($sku) {
                $query->where('sku', $sku);
            } else {
                $query->where('name', $name);
            }

            if ($query->exists()) {
                $skipped++;
                continue;
            }

            Product::create([
                'supplier_id'  => $supplier->id,
                'name'         => $name,
                'sku'          => $sku,
                'brand'        => $item['brand'] ?? null,
                'collection'   => $item['collection'] ?? null,
                'description'  => $item['description'] ?? null,
                'materials'    => $item['materials'] ?? null,
                'finishes'     => $item['finishes'] ?? null,
                'colors'       => $item['colors'] ?? null,
                'dimensions'   => $item['dimensions'] ?? null,
                'price_list'   => $item['price_list'] ?? null,
                'source_url'   => $item['url'] ?? $item['source_url'] ?? null,
                'is_active'    => false,
                'is_available' => false,
                'notes'        => 'Importato via analisi web — completare con listino prezzi.',
            ]);
            $created++;

            $this->editableItems[$idx]['imported'] = true;
        }

        $this->record->update([
            'items'          => array_values($this->editableItems),
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
