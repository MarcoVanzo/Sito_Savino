<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Resources\HeroSlideResource;
use App\Models\HeroSlide;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class HomepageSettingsPage extends BaseSettingsPage implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Homepage';

    protected static ?string $title = 'Impostazioni Homepage';

    protected static ?int $navigationSort = 64;

    protected static ?string $slug = 'settings/homepage';

    protected static string $view = 'filament.pages.homepage-settings-page';

    public string $activeTab = 'settings';

    public function mount(): void
    {
        parent::mount();
        $this->activeTab = request()->query('tab', 'settings');
    }

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

    public function table(Table $table): Table
    {
        return $table
            ->query(
                HeroSlide::query()->with('media')
            )
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('image')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->label('Immagine')
                    ->collection('hero-slides')
                    ->circular(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subtitle')
                    ->label('Sottotitolo')
                    ->limit(40),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Attiva'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (HeroSlide $record): string => HeroSlideResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('Nuova Slide Hero')
                    ->icon('heroicon-o-plus')
                    ->url(HeroSlideResource::getUrl('create')),
            ]);
    }
}
