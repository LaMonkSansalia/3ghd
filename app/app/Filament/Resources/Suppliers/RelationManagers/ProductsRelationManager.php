<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Prodotti';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_code')
                    ->label('Cod.')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('name')
                    ->label('Prodotto')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('tipo_prodotto')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'campionato' => 'Campionato',
                        'a_listino'  => 'A listino',
                        default      => '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'campionato' => 'success',
                        'a_listino'  => 'info',
                        default      => 'gray',
                    }),

                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->placeholder('—'),

                TextColumn::make('price_list')
                    ->label('Listino')
                    ->money('EUR')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('create_product')
                    ->label('Nuovo prodotto')
                    ->icon('heroicon-o-plus')
                    ->color('teal')
                    ->url(fn (): string => ProductResource::getUrl('create') . '?' . http_build_query(['supplier_id' => $this->ownerRecord->id])),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
