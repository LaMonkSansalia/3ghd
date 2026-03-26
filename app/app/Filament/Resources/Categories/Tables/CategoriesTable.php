<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Categoria')
                    ->formatStateUsing(fn (string $state, $record) => $record->parent_id ? '└─ ' . $state : $state)
                    ->weight(fn ($record) => $record->parent_id ? 'normal' : 'semibold')
                    ->searchable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sort_order')
                    ->label('Ord.')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('products_count')
                    ->label('Prodotti')
                    ->counts('products')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort(fn ($query) => $query->orderByRaw('COALESCE(parent_id, id)')->orderBy('sort_order'))
            ->filters([
                //
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
