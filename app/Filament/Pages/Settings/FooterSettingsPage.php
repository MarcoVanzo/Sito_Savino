<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

class FooterSettingsPage extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Footer';

    protected static ?string $title = 'Impostazioni Footer';

    protected static ?int $navigationSort = 63;

    protected static ?string $slug = 'settings/footer';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('footer_tagline')->label('Tagline')->rows(3),
                TextInput::make('footer_copyright')->label('Copyright')->helperText('{year} = anno dinamico'),
                TextInput::make('footer_piva')->label('P.IVA'),
            ])->statePath('data');
    }
}
