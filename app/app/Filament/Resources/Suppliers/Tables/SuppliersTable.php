<?php

namespace App\Filament\Resources\Suppliers\Tables;

use App\Jobs\DiscoverWebsiteJob;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
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
                    ->sortable(),

                TextColumn::make('catalog_format')
                    ->label('Formato')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pdf'    => 'danger',
                        'excel', 'csv' => 'success',
                        'web'    => 'info',
                        default  => 'gray',
                    }),

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

                TextColumn::make('last_imported_at')
                    ->label('Ultimo import')
                    ->since()
                    ->placeholder('Mai')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Attivo')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Stato')
                    ->placeholder('Tutti'),
            ])
            ->recordActions([
                Action::make('analyze_website')
                    ->label('Analizza sito')
                    ->icon('heroicon-o-globe-alt')
                    ->color('info')
                    ->visible(fn ($record) => ! empty($record->website))
                    ->requiresConfirmation()
                    ->modalHeading('Analizza presenza online')
                    ->modalDescription(fn ($record) => "Verrà avviato un crawl di {$record->website} per scoprire prodotti e collezioni. Risultati in \"Analisi Siti\".")
                    ->action(function ($record) {
                        DiscoverWebsiteJob::dispatch($record->id, $record->website);
                        Notification::make()
                            ->title('Analisi avviata')
                            ->body("Crawl di {$record->website} in esecuzione.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
