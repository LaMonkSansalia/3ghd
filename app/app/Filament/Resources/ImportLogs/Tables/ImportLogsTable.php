<?php

namespace App\Filament\Resources\ImportLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\BadgeColumn;
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

                BadgeColumn::make('format')
                    ->label('Formato')
                    ->colors([
                        'danger'  => 'pdf',
                        'success' => ['csv', 'excel'],
                        'info'    => 'web',
                        'warning' => 'images',
                        'gray'    => 'manual',
                    ]),

                BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'gray'    => 'pending',
                        'warning' => 'processing',
                        'success' => 'done',
                        'danger'  => 'failed',
                    ])
                    ->icons([
                        'heroicon-o-clock'        => 'pending',
                        'heroicon-o-arrow-path'   => 'processing',
                        'heroicon-o-check-circle' => 'done',
                        'heroicon-o-x-circle'     => 'failed',
                    ]),

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
