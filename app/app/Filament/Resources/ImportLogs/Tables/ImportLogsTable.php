<?php

namespace App\Filament\Resources\ImportLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('supplier.name')
                    ->label('Fornitore')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('format')
                    ->label('Formato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pdf'    => 'danger',
                        'csv'    => 'success',
                        'excel'  => 'success',
                        'web'    => 'info',
                        'images' => 'warning',
                        default  => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'    => 'gray',
                        'processing' => 'warning',
                        'done'       => 'success',
                        'failed'     => 'danger',
                        default      => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'pending'    => 'heroicon-o-clock',
                        'processing' => 'heroicon-o-arrow-path',
                        'done'       => 'heroicon-o-check-circle',
                        'failed'     => 'heroicon-o-x-circle',
                        default      => 'heroicon-o-question-mark-circle',
                    }),

                TextColumn::make('total_rows')
                    ->label('Righe')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('imported_count')
                    ->label('Nuovi')
                    ->sortable()
                    ->alignCenter()
                    ->color('success'),

                TextColumn::make('updated_count')
                    ->label('Aggiornati')
                    ->sortable()
                    ->alignCenter()
                    ->color('info'),

                TextColumn::make('skipped_count')
                    ->label('Saltati')
                    ->sortable()
                    ->alignCenter()
                    ->color('gray'),

                TextColumn::make('errors_count')
                    ->label('Errori')
                    ->sortable()
                    ->alignCenter()
                    ->color(fn ($record) => $record?->errors_count > 0 ? 'danger' : 'gray'),

                TextColumn::make('file_name')
                    ->label('File')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('started_at')
                    ->label('Avviato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since()
                    ->toggleable(),

                TextColumn::make('completed_at')
                    ->label('Completato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since()
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'    => 'In attesa',
                        'processing' => 'In corso',
                        'done'       => 'Completato',
                        'failed'     => 'Fallito',
                    ]),

                SelectFilter::make('supplier_id')
                    ->label('Fornitore')
                    ->relationship('supplier', 'name'),

                SelectFilter::make('format')
                    ->options([
                        'csv'    => 'CSV',
                        'excel'  => 'Excel',
                        'pdf'    => 'PDF',
                        'web'    => 'Web',
                        'images' => 'Immagini',
                        'manual' => 'Manuale',
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
