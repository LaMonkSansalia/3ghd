<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use App\Models\Supplier;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informazioni principali')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome prodotto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('supplier_id')
                            ->label('Fornitore')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('category_id')
                            ->label('Categoria')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        TextInput::make('sku')
                            ->label('Codice / SKU')
                            ->maxLength(100),

                        TextInput::make('brand')
                            ->label('Brand')
                            ->maxLength(100),

                        TextInput::make('collection')
                            ->label('Collezione / Serie')
                            ->maxLength(150)
                            ->placeholder('es. Seta, Lab 13, Office Time')
                            ->helperText('Raggruppamento commerciale del fornitore')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(3)
                            ->columnSpanFull(),

                        TagsInput::make('tags')
                            ->label('Tag')
                            ->placeholder('moderno, living, angolare...')
                            ->columnSpanFull(),
                    ]),

                Section::make('Materiali e Finiture')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        KeyValue::make('materials')
                            ->label('Materiali per componente')
                            ->keyLabel('Componente (es. anta, struttura, top)')
                            ->valueLabel('Materiale (es. MDF laccato, vetro)')
                            ->addButtonLabel('+ Aggiungi componente')
                            ->columnSpanFull(),

                        TagsInput::make('finishes')
                            ->label('Finiture disponibili')
                            ->placeholder('Laccato opaco, Impiallacciato rovere, Vetro...')
                            ->helperText('Texture e superficie'),

                        TagsInput::make('colors')
                            ->label('Colori disponibili')
                            ->placeholder('Bianco, Grigio Cenere, Antracite...')
                            ->helperText('Tinte e varianti colore'),
                    ]),

                Section::make('Dimensioni')
                    ->columns(4)
                    ->collapsed()
                    ->schema([
                        TextInput::make('dimensions.l')->label('Larghezza (cm)')->numeric()->minValue(0),
                        TextInput::make('dimensions.p')->label('Profondità (cm)')->numeric()->minValue(0),
                        TextInput::make('dimensions.h')->label('Altezza (cm)')->numeric()->minValue(0),
                        TextInput::make('dimensions.peso')->label('Peso (kg)')->numeric()->minValue(0),
                    ]),

                Section::make('Prezzi')
                    ->columns(3)
                    ->schema([
                        TextInput::make('price_list')
                            ->label('Prezzo listino (€)')
                            ->numeric()->prefix('€')->minValue(0),

                        TextInput::make('cost')
                            ->label('Prezzo acquisto (€) — PRIVATO')
                            ->numeric()->prefix('€')->minValue(0)
                            ->helperText('Visibile solo allo staff. Non mostrato al cliente.'),

                        TextInput::make('markup_override')
                            ->label('Markup override')
                            ->numeric()
                            ->placeholder('Es. 1.35 (+35%)')
                            ->helperText('Lascia vuoto per usare il markup del fornitore'),
                    ]),

                Section::make('Note interne')
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')->label('Note staff')->rows(3)->columnSpanFull(),
                    ]),

                Section::make('Stato')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_active')->label('Attivo')->default(true),
                        Toggle::make('is_available')->label('Disponibile')->default(true),
                        Toggle::make('is_featured')->label('In evidenza')->default(false),
                    ]),
            ]);
    }
}
