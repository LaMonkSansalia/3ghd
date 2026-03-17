<?php

namespace App\Filament\Resources\WebDiscoveries;

use App\Filament\Resources\WebDiscoveries\Pages\ListWebDiscoveries;
use App\Filament\Resources\WebDiscoveries\Pages\ViewWebDiscovery;
use App\Filament\Resources\WebDiscoveries\Tables\WebDiscoveriesTable;
use App\Models\WebDiscovery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebDiscoveryResource extends Resource
{
    protected static ?string $model = WebDiscovery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Analisi Siti';
    protected static \UnitEnum|string|null $navigationGroup = 'Fornitori';
    protected static ?int    $navigationSort  = 20;

    protected static ?string $modelLabel       = 'Analisi sito';
    protected static ?string $pluralModelLabel = 'Analisi siti';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return WebDiscoveriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebDiscoveries::route('/'),
            'view'  => ViewWebDiscovery::route('/{record}'),
        ];
    }
}
