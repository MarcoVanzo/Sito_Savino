<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

class HomepageSettingsPage extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Homepage';

    protected static ?string $title = 'Impostazioni Homepage';

    protected static ?int $navigationSort = 64;

    protected static ?string $slug = 'settings/homepage';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Hero')->schema([
                    TextInput::make('hero_title')->label('Titolo'),
                    TextInput::make('hero_subtitle')->label('Accento'),
                    TextInput::make('hero_tagline')->label('Claim'),
                    TextInput::make('hero_video_url')->label('Video Background URL')->url()->helperText('Inserisci il link diretto a un file .mp4. Lascia vuoto per usare le immagini.'),
                ]),
                Section::make('CTA Hero')->schema([
                    TextInput::make('hero_cta1_label')->label('CTA Primario'),
                    TextInput::make('hero_cta1_url')->label('URL Primario'),
                    TextInput::make('hero_cta2_label')->label('CTA Secondario'),
                    TextInput::make('hero_cta2_url')->label('URL Secondario'),
                ])->columns(2),
                Section::make('Stats')->schema([
                    Textarea::make('stats')->label('Statistiche (JSON)')->rows(5),
                    TextInput::make('stats_title')->label('Titolo Sezione'),
                    TextInput::make('stats_subtitle')->label('Sottotitolo Sezione'),
                ]),
                Section::make('Banners')->schema([
                    TextInput::make('cta_ticketing_title')->label('Ticketing Titolo'),
                    TextInput::make('cta_ticketing_text')->label('Ticketing Testo'),
                    TextInput::make('cta_ticketing_url')->label('Ticketing URL'),
                    TextInput::make('cta_shop_title')->label('Shop Titolo'),
                    TextInput::make('cta_shop_text')->label('Shop Testo'),
                    TextInput::make('cta_shop_url')->label('Shop URL'),
                ])->columns(2),
            ])->statePath('data');
    }
}
