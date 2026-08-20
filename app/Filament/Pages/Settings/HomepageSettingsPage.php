<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Resources\HeroSlideResource;
use App\Models\HeroSlide;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
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

    /**
     * I testi della homepage sono già in archivio come JSON per lingua: senza
     * dichiararli qui il modulo ne caricherebbe la sola versione italiana e il
     * primo salvataggio cancellerebbe l'inglese.
     *
     * Titolo e accento del hero restano fuori: sono il nome della società.
     */
    protected function translatableKeys(): array
    {
        return [
            'hero_tagline', 'hero_cta1_label', 'hero_cta2_label',
            'stats', 'stats_title', 'stats_subtitle',
            'cta_ticketing_title', 'cta_ticketing_text',
            'cta_shop_title', 'cta_shop_text',
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Hero')->schema([
                    TextInput::make('hero_title')->label('Titolo'),
                    TextInput::make('hero_subtitle')->label('Accento'),
                    ...$this->tradotto('hero_tagline', 'Claim'),
                    TextInput::make('hero_video_url')->label('Video Background URL')->url()->helperText('Inserisci il link diretto a un file .mp4. Lascia vuoto per usare le immagini.'),
                ])->columns(2),
                Section::make('CTA Hero')->schema([
                    ...$this->tradotto('hero_cta1_label', 'CTA Primario'),
                    TextInput::make('hero_cta1_url')->label('URL Primario'),
                    ...$this->tradotto('hero_cta2_label', 'CTA Secondario'),
                    TextInput::make('hero_cta2_url')->label('URL Secondario'),
                ])->columns(2),
                Section::make('Stats')->schema([
                    ...array_map(
                        fn (string $locale) => $this->statistiche($locale),
                        $this->locales(),
                    ),
                    ...$this->tradotto('stats_title', 'Titolo Sezione'),
                    ...$this->tradotto('stats_subtitle', 'Sottotitolo Sezione'),
                ])->columns(2),
                Section::make('Banners')->schema([
                    ...$this->tradotto('cta_ticketing_title', 'Ticketing Titolo'),
                    ...$this->tradotto('cta_ticketing_text', 'Ticketing Testo'),
                    TextInput::make('cta_ticketing_url')->label('Ticketing URL')->columnSpanFull(),
                    ...$this->tradotto('cta_shop_title', 'Shop Titolo'),
                    ...$this->tradotto('cta_shop_text', 'Shop Testo'),
                    TextInput::make('cta_shop_url')->label('Shop URL')->columnSpanFull(),
                ])->columns(2),
            ])->statePath('data');
    }

    /**
     * Un campo per lingua sulla stessa impostazione.
     *
     * @return list<TextInput>
     */
    private function tradotto(string $key, string $label): array
    {
        return array_map(
            fn (string $locale): TextInput => TextInput::make("{$key}.{$locale}")
                ->label($label.' ('.strtoupper($locale).')'),
            $this->locales(),
        );
    }

    private function statistiche(string $locale): Repeater
    {
        return Repeater::make("stats.{$locale}")
            ->label('Statistiche ('.strtoupper($locale).')')
            ->schema([
                TextInput::make('value')->label('Valore')->required()->placeholder('es. 40+'),
                TextInput::make('label')->label('Etichetta')->required()->placeholder('es. Anni di Storia'),
                TextInput::make('icon')->label('Icona (emoji)')->placeholder('es. 🏆'),
            ])
            ->columns(3)
            ->collapsible()
            ->columnSpanFull()
            // Lo stato resta un elenco: a incapsularlo in JSON ci pensa il
            // salvataggio, che riunisce le lingue in un'unica impostazione.
            ->formatStateUsing(fn ($state) => is_array($state) ? $state : (json_decode($state ?? '[]', true) ?: []));
    }

    /** @return list<string> */
    private function locales(): array
    {
        return array_values(config('app.supported_locales', ['it', 'en']));
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
