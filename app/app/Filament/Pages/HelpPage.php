<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class HelpPage extends Page
{
    protected string $view = 'filament.admin.pages.help-page';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationLabel = 'Guida';
    protected static ?int $navigationSort = 99;
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?string $title = 'Guida Studio3GHD';
}
