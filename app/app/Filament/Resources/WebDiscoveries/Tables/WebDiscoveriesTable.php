<?php

namespace App\Filament\Resources\WebDiscoveries\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WebDiscoveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Fornitore')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('entry_url')
                    ->label('URL analizzato')
                    ->limit(40)
                    ->url(fn ($record) => $record->entry_url)
                    ->openUrlInNewTab(),

                BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'gray'    => 'pending',
                        'warning' => 'crawling',
                        'success' => 'done',
                        'danger'  => 'failed',
                    ])
                    ->icons([
                        'heroicon-o-clock'        => 'pending',
                        'heroicon-o-arrow-path'   => 'crawling',
                        'heroicon-o-check-circle' => 'done',
                        'heroicon-o-x-circle'     => 'failed',
                    ]),

                TextColumn::make('pages_crawled')
                    ->label('Pagine')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('items_found')
                    ->label('Trovati')
                    ->alignCenter()
                    ->sortable()
                    ->color('success'),

                TextColumn::make('items_imported')
                    ->label('Importati')
                    ->alignCenter()
                    ->sortable()
                    ->color('info'),

                TextColumn::make('started_at')
                    ->label('Data analisi')
                    ->dateTime('d/m/Y H:i')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'  => 'In attesa',
                        'crawling' => 'In corso',
                        'done'     => 'Completata',
                        'failed'   => 'Fallita',
                    ]),

                SelectFilter::make('supplier_id')
                    ->label('Fornitore')
                    ->relationship('supplier', 'name'),
            ])
            ->recordUrl(fn ($record) => \App\Filament\Resources\WebDiscoveries\WebDiscoveryResource::getUrl('view', ['record' => $record]))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
