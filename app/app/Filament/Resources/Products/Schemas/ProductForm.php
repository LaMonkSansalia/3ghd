<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // ── Main (col 1-2) ────────────────────────────────────────
                Group::make()
                    ->columnSpan(2)
                    ->schema([
                        Select::make('tipo_prodotto')
                            ->label('Tipo prodotto')
                            ->options([
                                'campionato' => 'Campionato — configurazione scelta con codice fornitore definito',
                                'a_listino'  => 'A listino — prodotto finito a prezzo fisso (sedia, tavolo, complemento)',
                            ])
                            ->placeholder('Seleziona il tipo...')
                            ->native(false)
                            ->required()
                            ->live(),

                        Placeholder::make('product_code')
                            ->label('Codice interno')
                            ->content(fn ($record) => $record?->product_code ?? '(generato al salvataggio)'),

                        TextInput::make('name')
                            ->label('Nome prodotto')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label(fn (Get $get): string => match ($get('tipo_prodotto')) {
                                'campionato' => 'Campionatura',
                                default      => 'Descrizione',
                            })
                            ->placeholder(fn (Get $get): string => match ($get('tipo_prodotto')) {
                                'campionato' => 'Composizione, moduli, categoria rivestimento — es. 3Posti SX/DX estraibile + CL, Tessuto cat. M1. Includi varianti SX/DX e configurazioni disponibili.',
                                default      => 'Caratteristiche, materiali, note commerciali',
                            })
                            ->rows(4)
                            ->live(),

                        SpatieMediaLibraryFileUpload::make('images')
                            ->label('Galleria immagini')
                            ->collection('images')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Prima immagine = thumbnail. Max 5 MB per file. JPG, PNG, WebP.'),
                    ]),

                // ── Sidebar (col 3) ───────────────────────────────────────
                Group::make()
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Classificazione')
                            ->schema([
                                Select::make('supplier_id')
                                    ->label('Fornitore')
                                    ->relationship('supplier', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Nome fornitore')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('website')
                                            ->label('Sito web')
                                            ->url()
                                            ->maxLength(255),
                                        TextInput::make('markup_default')
                                            ->label('Markup default')
                                            ->numeric()
                                            ->default(1.35),
                                    ])
                                    ->createOptionUsing(fn (array $data) => Supplier::create($data)->id),

                                Select::make('category_id')
                                    ->label('Categoria')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Nome categoria')
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('parent_id')
                                            ->label('Categoria padre (opzionale)')
                                            ->options(fn () => Category::whereNull('parent_id')->pluck('name', 'id'))
                                            ->nullable(),
                                    ])
                                    ->createOptionUsing(fn (array $data) => Category::create($data)->id),

                                TextInput::make('collection')
                                    ->label('Collezione / Serie')
                                    ->maxLength(150)
                                    ->placeholder('es. Seta, Lab 13, Office Time'),

                                TextInput::make('sku')
                                    ->label('Codice fornitore')
                                    ->maxLength(100)
                                    ->helperText('Codice originale del fornitore, copiare verbatim'),
                            ]),

                        Section::make('Prezzi')
                            ->schema([
                                TextInput::make('cost')
                                    ->label('Costo acquisto (€) — PRIVATO')
                                    ->numeric()
                                    ->prefix('€')
                                    ->minValue(0)
                                    ->helperText('Visibile solo allo staff.'),

                                TextInput::make('price_list')
                                    ->label('Prezzo listino (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->minValue(0),

                                TextInput::make('markup_override')
                                    ->label('Markup override')
                                    ->numeric()
                                    ->placeholder('Es. 1.35 (+35%)')
                                    ->helperText('Vuoto = usa markup del fornitore'),

                                Placeholder::make('prezzo_cliente')
                                    ->label('Prezzo cliente (€)')
                                    ->content(fn ($record): string => ($record && $record->cost)
                                        ? '€ ' . number_format((float) $record->cost * $record->effectiveMarkup(), 2, ',', '.')
                                        : '—'
                                    ),
                            ]),

                        Section::make('Stato')
                            ->schema([
                                Toggle::make('is_active')->label('Attivo')->default(true),
                                Toggle::make('is_available')->label('Disponibile')->default(true),
                                Toggle::make('is_featured')->label('In evidenza')->default(false),
                            ]),
                    ]),

                // ── Bottom full-width (collapsed) ─────────────────────────
                Section::make('Attributi prodotto')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        KeyValue::make('materials')
                            ->label('')
                            ->keyLabel('Attributo (es. Rivestimento, Colore, Gambe, Struttura, Materiale top)')
                            ->valueLabel('Valore/i')
                            ->addButtonLabel('+ Aggiungi attributo')
                            ->columnSpanFull(),
                    ]),

                Section::make('Dimensioni')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Repeater::make('dimensions')
                            ->label('')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Etichetta')
                                    ->placeholder('es. Corpo principale, Pouf, Isola'),

                                TextInput::make('l')
                                    ->label('Largh. cm')
                                    ->numeric()
                                    ->minValue(0),

                                TextInput::make('p')
                                    ->label('Prof. cm')
                                    ->numeric()
                                    ->minValue(0),

                                TextInput::make('h')
                                    ->label('Alt. cm')
                                    ->numeric()
                                    ->minValue(0),

                                TextInput::make('diametro')
                                    ->label('Diametro cm')
                                    ->numeric()
                                    ->minValue(0),

                                Textarea::make('note_dim')
                                    ->label('Note misure')
                                    ->rows(2)
                                    ->placeholder('es. P_seduta: 95, P_aperto: 155, range: 240–290')
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->addActionLabel('+ Aggiungi elemento')
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => $state['label'] ?? 'Elemento')
                            ->columnSpanFull(),
                    ]),

                Section::make('Note e Tag')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Note staff')
                            ->rows(3)
                            ->helperText('Visibile solo allo staff. Es. tempi di consegna, disponibilità limitata, accordi con il fornitore.')
                            ->columnSpanFull(),

                        TagsInput::make('tags')
                            ->label('Tag')
                            ->placeholder('moderno, living, angolare...')
                            ->suggestions(fn (): array => Product::query()
                                ->pluck('tags')
                                ->flatten()
                                ->filter()
                                ->unique()
                                ->sort()
                                ->values()
                                ->toArray()
                            )
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
