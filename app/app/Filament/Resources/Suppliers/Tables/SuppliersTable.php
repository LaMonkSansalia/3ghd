<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Fornitore')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->badgeColor()),

                TextColumn::make('website')
                    ->label('Sito')
                    ->limit(28)
                    ->url(fn ($record) => $record->website)
                    ->openUrlInNewTab()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('products_count')
                    ->label('Prodotti')
                    ->counts('products')
                    ->sortable(),

                TextColumn::make('markup_default')
                    ->label('Markup')
                    ->formatStateUsing(fn ($state) => '+' . round(($state - 1) * 100) . '%')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Attivo')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([])
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
