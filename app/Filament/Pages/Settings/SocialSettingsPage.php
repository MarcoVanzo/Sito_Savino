<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

class SocialSettingsPage extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationLabel = 'Social';

    protected static ?string $title = 'Impostazioni Social';

    protected static ?int $navigationSort = 62;

    protected static ?string $slug = 'settings/social';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Social Media')->schema([
                    TextInput::make('social_instagram')->label('Instagram')->url(),
                    TextInput::make('social_facebook')->label('Facebook')->url(),
                    TextInput::make('social_youtube')->label('YouTube')->url(),
                    TextInput::make('social_x')->label('X (Twitter)')->url(),
                    TextInput::make('social_tiktok')->label('TikTok')->url(),
                ]),
            ])->statePath('data');
    }
}
