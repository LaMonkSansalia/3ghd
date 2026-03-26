<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
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

                        Placeholder::make('gallery_preview')
                            ->label('Anteprima (clicca per ingrandire)')
                            ->content(function ($record): \Illuminate\Support\HtmlString {
                                if (! $record) {
                                    return new \Illuminate\Support\HtmlString('<em style="color:#9ca3af;font-size:0.875rem">Le anteprime saranno visibili dopo il salvataggio.</em>');
                                }
                                $images = $record->getMedia('images');
                                if ($images->isEmpty()) {
                                    return new \Illuminate\Support\HtmlString('<em style="color:#9ca3af;font-size:0.875rem">Nessuna immagine caricata.</em>');
                                }
                                $html = '<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.25rem;">';
                                foreach ($images as $media) {
                                    $thumb = $media->getUrl('thumb');
                                    $full  = $media->getUrl();
                                    $html .= '<a href="' . e($full) . '" class="glightbox" data-gallery="product-' . $record->id . '" data-title="' . e($media->name) . '">'
                                           . '<img src="' . e($thumb) . '" style="width:80px;height:60px;object-fit:cover;border-radius:0.375rem;border:1px solid #e5e7eb;cursor:zoom-in;" />'
                                           . '</a>';
                                }
                                $html .= '</div>';
                                $html .= '<script>document.addEventListener("DOMContentLoaded",function(){if(window.GLightbox)window.GLightbox({selector:".glightbox"});});</script>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->visible(fn ($record) => $record !== null),
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
                                    ->live()
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
                                    ->live(debounce: 600)
                                    ->helperText('Visibile solo allo staff.'),

                                TextInput::make('price_list')
                                    ->label('Prezzo vendita (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->minValue(0),

                                TextInput::make('markup_override')
                                    ->label('Markup override')
                                    ->numeric()
                                    ->placeholder('Es. 1.35 (+35%)')
                                    ->live(debounce: 600)
                                    ->helperText('Vuoto = usa markup del fornitore'),

                                Placeholder::make('prezzo_cliente')
                                    ->label('Prezzo cliente (€)')
                                    ->content(function (Get $get): string {
                                        $cost   = (float) ($get('cost') ?? 0);
                                        $markup = (float) ($get('markup_override') ?? 0);

                                        if (! $markup) {
                                            $supplierId = $get('supplier_id');
                                            $supplier   = $supplierId ? \App\Models\Supplier::find($supplierId) : null;
                                            $markup     = (float) ($supplier?->markup_default ?? 1.35);
                                        }

                                        if (! $cost) {
                                            return '—';
                                        }

                                        return '€ ' . number_format($cost * $markup, 2, ',', '.');
                                    }),
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
                        Repeater::make('materials')
                            ->label('')
                            ->schema([
                                Select::make('key')
                                    ->label('Attributo')
                                    ->options([
                                        'Rivestimento'  => 'Rivestimento',
                                        'Colore'        => 'Colore',
                                        'Gambe'         => 'Gambe',
                                        'Struttura'     => 'Struttura',
                                        'Materiale top' => 'Materiale top',
                                        'Profilo'       => 'Profilo',
                                        'Imbottitura'   => 'Imbottitura',
                                        'Seduta'        => 'Seduta',
                                        'Finitura'      => 'Finitura',
                                        'Altro'         => '+ Altro (personalizzato)',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->required(),

                                TextInput::make('value')
                                    ->label('Valore/i')
                                    ->placeholder('es. Tessuto cat. M1, Pelle Buff, Rovere naturale')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('+ Aggiungi attributo')
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull()
                            ->afterStateHydrated(function (Repeater $component, mixed $state): void {
                                // Converti vecchio formato {key: value} in [{key, value}]
                                if (! is_array($state) || empty($state)) {
                                    return;
                                }
                                // Se già nel nuovo formato (array di oggetti con 'key'), non fare nulla
                                if (isset($state[0]) && is_array($state[0]) && array_key_exists('key', $state[0])) {
                                    return;
                                }
                                // Vecchio formato: array associativo
                                $converted = [];
                                foreach ($state as $k => $v) {
                                    if (is_string($k)) {
                                        $converted[] = ['key' => $k, 'value' => (string) $v];
                                    }
                                }
                                if (! empty($converted)) {
                                    $component->state($converted);
                                }
                            }),
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
