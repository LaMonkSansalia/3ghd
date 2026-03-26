<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->getStateUsing(fn (\App\Models\Product $record): string => $record->getFirstMediaUrl('images', 'thumb'))
                    ->width(80)
                    ->height(60)
                    ->extraImgAttributes(['class' => 'object-cover rounded'])
                    ->toggleable(),

                TextColumn::make('product_code')
                    ->label('Cod.')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Prodotto')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('supplier.name')
                    ->label('Fornitore')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('price_list')
                    ->label('Listino')
                    ->money('EUR')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

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
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('collection')
                    ->label('Collezione')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sku')
                    ->label('Cod. fornitore')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Aggiornato')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->searchPlaceholder('Cerca per nome, SKU, fornitore...')
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Fornitore')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('tipo_prodotto')
                    ->label('Tipo prodotto')
                    ->options([
                        'campionato' => 'Campionato',
                        'a_listino'  => 'A listino',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
