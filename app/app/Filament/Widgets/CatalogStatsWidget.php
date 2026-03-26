<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\Supplier;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalProducts  = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $draftProducts  = Product::where('is_active', false)->count();

        $totalSuppliers = Supplier::where('is_active', true)->count();

        $lastProduct = Product::latest('updated_at')->first();

        return [
            Stat::make('Prodotti totali', $totalProducts)
                ->description("$activeProducts attivi · $draftProducts bozze")
                ->descriptionIcon('heroicon-m-cube')
                ->color('teal')
                ->url('/admin/products'),

            Stat::make('Bozze da completare', $draftProducts)
                ->description('Prodotti non ancora attivi')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($draftProducts > 0 ? 'warning' : 'success')
                ->url('/admin/products'),

            Stat::make('Fornitori attivi', $totalSuppliers)
                ->description('Fornitori con catalogo')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('blue')
                ->url('/admin/suppliers'),

            Stat::make('Ultimo aggiornamento', $lastProduct
                ? $lastProduct->updated_at->diffForHumans()
                : '—')
                ->description($lastProduct ? $lastProduct->name : 'Nessun prodotto')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray')
                ->url('/admin/products'),
        ];
    }
}
