<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use App\Models\Category;
use App\Models\Supplier;

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
                    ->placeholder('—'),

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
                    ->color('gray'),

                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('price_list')
                    ->label('Listino')
                    ->money('EUR')
                    ->sortable()
                    ->placeholder('—'),

                ToggleColumn::make('is_active')
                    ->label('Attivo')
                    ->sortable(),

                ToggleColumn::make('is_available')
                    ->label('Disponibile')
                    ->sortable(),

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

                TernaryFilter::make('is_active')
                    ->label('Stato')
                    ->placeholder('Tutti')
                    ->trueLabel('Solo attivi')
                    ->falseLabel('Solo inattivi'),

                TernaryFilter::make('is_available')
                    ->label('Disponibilità')
                    ->placeholder('Tutti')
                    ->trueLabel('Disponibili')
                    ->falseLabel('Non disponibili'),

                TernaryFilter::make('is_featured')
                    ->label('In evidenza')
                    ->placeholder('Tutti'),
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
