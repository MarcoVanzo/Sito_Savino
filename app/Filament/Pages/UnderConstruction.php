<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class UnderConstruction extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $title = 'In Costruzione';
    protected static string $view = 'filament.pages.under-construction';
    
    // We don't want this to appear in the navigation by itself
    protected static bool $shouldRegisterNavigation = false;
}
