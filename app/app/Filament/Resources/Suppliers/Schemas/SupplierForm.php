<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati fornitore')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome fornitore / brand')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('website')
                            ->label('Sito web')
                            ->url()
                            ->maxLength(255),

                        Select::make('catalog_format')
                            ->label('Formato catalogo')
                            ->options([
                                'pdf'    => 'PDF',
                                'csv'    => 'CSV',
                                'excel'  => 'Excel (.xlsx)',
                                'web'    => 'Sito web',
                                'images' => 'Immagini',
                                'mixed'  => 'Misto',
                            ])
                            ->default('mixed'),
                    ]),

                Section::make('Contatto')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextInput::make('contact_name')
                            ->label('Nome referente')
                            ->maxLength(255),
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label('Telefono')
                            ->tel()
                            ->maxLength(50),
                    ]),

                Section::make('Pricing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('markup_default')
                            ->label('Markup default')
                            ->numeric()
                            ->default(1.35)
                            ->helperText('Es. 1.35 = +35% sul listino. Applicato a tutti i prodotti senza override.'),
                        Toggle::make('is_active')
                            ->label('Fornitore attivo')
                            ->default(true),
                    ]),

                Section::make('Note')
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Note interne')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
