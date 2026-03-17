<?php

namespace App\Filament\Widgets;

use App\Models\ImportLog;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\WebDiscovery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalProducts   = Product::count();
        $activeProducts  = Product::where('is_active', true)->count();
        $draftProducts   = Product::where('is_active', false)->count();

        $totalSuppliers  = Supplier::where('is_active', true)->count();

        $lastImport = ImportLog::latest('completed_at')
            ->where('status', 'done')
            ->first();

        $lastDiscovery = WebDiscovery::latest('completed_at')
            ->where('status', 'done')
            ->first();

        return [
            Stat::make('Prodotti totali', $totalProducts)
                ->description("$activeProducts attivi · $draftProducts bozze")
                ->descriptionIcon('heroicon-m-cube')
                ->color('teal')
                ->url('/admin/products'),

            Stat::make('Fornitori attivi', $totalSuppliers)
                ->description('Fornitori con catalogo')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('blue')
                ->url('/admin/suppliers'),

            Stat::make('Ultimo import', $lastImport
                ? $lastImport->completed_at->diffForHumans()
                : 'Mai eseguito')
                ->description($lastImport
                    ? ($lastImport->supplier?->name ?? '—') . ' · ' . $lastImport->imported_count . ' prodotti'
                    : 'Usa Importa Catalogo per iniziare')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color($lastImport ? 'success' : 'gray')
                ->url('/admin/import-logs'),

            Stat::make('Ultima analisi web', $lastDiscovery
                ? $lastDiscovery->completed_at->diffForHumans()
                : 'Mai eseguita')
                ->description($lastDiscovery
                    ? ($lastDiscovery->supplier?->name ?? '—') . ' · ' . count($lastDiscovery->items ?? []) . ' elementi'
                    : 'Usa Analisi Siti per iniziare')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($lastDiscovery ? 'success' : 'gray')
                ->url('/admin/web-discoveries'),
        ];
    }
}
