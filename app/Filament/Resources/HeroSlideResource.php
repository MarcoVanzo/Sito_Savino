<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HeroSlideResource\Pages\CreateHeroSlide;
use App\Filament\Resources\HeroSlideResource\Pages\EditHeroSlide;
use App\Filament\Resources\HeroSlideResource\Pages\ListHeroSlides;
use App\Models\HeroSlide;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class HeroSlideResource extends Resource
{
    use Translatable;

    protected static ?string $model = HeroSlide::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Slide Hero';

    protected static ?string $pluralModelLabel = 'Slides Hero';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = 'Pagine & Extra';

    protected static ?int $navigationSort = 45;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Immagine')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->label('Immagine Slide')
                            ->collection('hero-slides')
                            ->image()
                            ->maxSize(5120)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1080')
                            ->imageResizeUpscale(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contenuto')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titolo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('subtitle')
                            ->label('Sottotitolo')
                            ->maxLength(255),
                        TextInput::make('cta_text')
                            ->label('Testo CTA')
                            ->maxLength(255),
                        // Niente ->url(): le CTA puntano quasi sempre a percorsi
                        // interni (es. /biglietteria), che la regola url() scarta.
                        TextInput::make('cta_url')
                            ->label('URL CTA')
                            ->helperText('Percorso interno (es. /biglietteria) o URL completo.')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Impostazioni')
                    ->schema([
                        TextInput::make('sort_order')
                            ->label('Ordine')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->label('Immagine')
                    ->collection('hero-slides')
                    ->circular(),
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subtitle')
                    ->label('Sottotitolo')
                    ->limit(40),
                ToggleColumn::make('is_active')
                    ->label('Attiva'),
                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHeroSlides::route('/'),
            'create' => CreateHeroSlide::route('/create'),
            'edit' => EditHeroSlide::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('media');
    }
}
