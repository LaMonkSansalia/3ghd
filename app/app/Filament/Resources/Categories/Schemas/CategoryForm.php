<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome categoria')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if (empty($get('slug'))) {
                            $set('slug', \Illuminate\Support\Str::slug($state));
                        }
                    }),

                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(120)
                    ->helperText('Generato automaticamente dal nome'),

                Select::make('parent_id')
                    ->label('Categoria padre')
                    ->options(Category::whereNull('parent_id')->orderBy('name')->pluck('name', 'id'))
                    ->nullable()
                    ->placeholder('— Nessuna (categoria radice) —')
                    ->helperText('Lascia vuoto per categoria di primo livello'),

                TextInput::make('sort_order')
                    ->label('Ordinamento')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->helperText('Numero più basso = prima nella lista'),
            ]);
    }
}
