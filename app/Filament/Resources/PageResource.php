<?php

namespace App\Filament\Resources;

use App\Enums\PageTemplate;
use App\Enums\PostStatus;
use App\Filament\Forms\PageTemplateForms;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class PageResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?string $model = Page::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Pagina';

    protected static ?string $pluralModelLabel = 'Pagine';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document';

    protected static string|\UnitEnum|null $navigationGroup = 'Pagine & Extra';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])
                    ->schema([
                        ...self::colonnaPrincipale(),

                        ...self::colonnaLaterale(),
                    ])->columnSpanFull(),
            ]);
    }

    /**
     * Colonna di sinistra: contenuto della pagina e impostazioni del suo
     * template.
     *
     * @return array<int, Component>
     */
    private static function colonnaPrincipale(): array
    {
        return [
            Group::make([
                Section::make('Contenuto Principale')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titolo')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->label('URL Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        RichEditor::make('content')
                            ->label('Contenuto')
                            ->columnSpanFull(),
                        Textarea::make('excerpt')
                            ->label('Riassunto (Excerpt)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Group::make([
                    // FORM: SOCIETA / ORGANIGRAMMA
                    Section::make('Impostazioni Pagina Società')
                        ->label('Dati Società / Organigramma')
                        ->schema(PageTemplateForms::getSocietaSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Organigramma)),

                    // FORM: STORIA
                    Section::make('Impostazioni Pagina Storia')
                        ->schema(PageTemplateForms::getStoriaSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Storia)),

                    // FORM: PALAZZETTO
                    Section::make('Impostazioni Pagina Palazzetto')
                        ->schema(PageTemplateForms::getPalazzettoSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Palazzetto)),

                    // FORM: SAFEGUARDING
                    Section::make('Impostazioni Pagina Safeguarding')
                        ->schema(PageTemplateForms::getSafeguardingSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Safeguarding)),

                    // FORM: CONTATTI (No outer Section/Card to prevent nested borders!)
                    Group::make(PageTemplateForms::getContattiSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Contatti)),

                    // FORM: TICKETING
                    Section::make('Impostazioni Pagina Biglietteria')
                        ->schema(PageTemplateForms::getTicketingSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Ticketing)),

                    // FORM: CLUB RACE
                    Section::make('Convenzioni per gli abbonati')
                        ->schema(PageTemplateForms::getConvenzioniSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Convenzioni)),

                    // FORM: PROGETTO AFFILIAZIONI
                    Section::make('Societa\' del progetto affiliazioni')
                        ->schema(PageTemplateForms::getAffiliazioniSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Affiliazioni)),

                    Section::make('Impostazioni Pagina Club Race')
                        ->schema(PageTemplateForms::getClubRaceSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::ClubRace)),

                    // FORM: SUMMER CAMP
                    Section::make('Impostazioni Pagina Summer Camp')
                        ->schema(PageTemplateForms::getSummerCampSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::SummerCamp)),

                    // FORM: PROGETTI SOCIALI
                    Section::make('Impostazioni Pagina Progetti Sociali')
                        ->schema(PageTemplateForms::getSocialeSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Sociale)),

                    // FORM: SPONSOR
                    Section::make('Impostazioni Pagina Sponsor')
                        ->schema(PageTemplateForms::getSponsorSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Sponsor)),

                    // FORM: SETTORE GIOVANILE
                    Section::make('Impostazioni Pagina Settore Giovanile')
                        ->schema(PageTemplateForms::getYouthSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Youth)),

                    // FORM: TALENT DAY
                    Section::make('Impostazioni Pagina Talent Day')
                        ->schema(PageTemplateForms::getTalentDaySchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::TalentDay)),

                    // FORM: COMUNICAZIONE
                    Section::make('Impostazioni Pagina Comunicazione')
                        ->schema(PageTemplateForms::getComunicazioneSchema())
                        ->visible(fn (Get $get) => PageTemplate::scelto($get('template'), PageTemplate::Comunicazione)),

                    // FORM: ISCRIZIONE EXPERIENCE (SUMMER CAMP)
                    Section::make('Pulsante Grafico Iscrizione (Experience)')
                        ->schema(PageTemplateForms::getCampEnrollmentSchema())
                        ->visible(fn (Get $get) => $get('slug') === 'iscrizione-experience'),

                    // FORM: DOUBLE FACE YOUTUBE EMBED
                    Section::make('Video YouTube Double Face')
                        ->schema(PageTemplateForms::getDoubleFaceSchema())
                        ->visible(fn (Get $get) => $get('slug') === 'double-face'),

                    // FORM: MAGAZINE PDF UPLOAD
                    Section::make('Archivio Edizioni Magazine')
                        ->schema(PageTemplateForms::getMagazineSchema())
                        ->visible(fn (Get $get) => $get('slug') === 'magazine'),

                    // FORM: PAGINE DI CONTENUTO (pulsante + galleria foto)
                    Section::make('Pulsante e Galleria Fotografica')
                        ->schema(PageTemplateForms::getContentPageSchema())
                        ->visible(fn (Get $get) => ($get('template') === null || PageTemplate::scelto($get('template'), PageTemplate::ContentPage, PageTemplate::Predefinito)) && $get('slug') !== 'iscrizione-experience'),
                ])->columnSpanFull(),
            ])->columnSpan(['lg' => 2]),
        ];
    }

    /**
     * Colonna di destra: pubblicazione, SEO, copertina e galleria.
     *
     * @return array<int, Component>
     */
    private static function colonnaLaterale(): array
    {
        return [
            Group::make([
                Section::make('Stato & Associazione')
                    ->schema([
                        Select::make('status')
                            ->label('Stato Pubblicazione')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Published)
                            ->required(),
                        Select::make('template')
                            ->label('Template Pagina')
                            ->options(PageTemplate::opzioni())
                            // Home, Contatti… non sono fra le voci ma la loro
                            // pagina li tiene: Filament 4 rifiuterebbe al
                            // salvataggio un valore fuori dalla tendina.
                            ->in(fn (?Page $record): array => array_values(array_filter([
                                ...array_keys(PageTemplate::opzioni()),
                                $record?->template,
                            ])))
                            ->live()
                            ->nullable(),
                        Select::make('parent_id')
                            ->relationship('parent', 'title', fn (Builder $query, $record) => $record ? $query->where('id', '!=', $record->id) : $query)
                            ->label('Pagina Genitore')
                            ->searchable(),
                        Select::make('author_id')
                            ->label('Autore')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->default(fn () => auth()->id()),
                    ]),

                Section::make('Immagine di Copertina')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Copertina')
                            ->collection('cover')
                            ->image()
                            ->maxSize(5120),
                    ]),

                Section::make('Impostazioni SEO')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta Titolo (SEO)')
                            ->maxLength(255),
                        Textarea::make('meta_description')
                            ->label('Meta Descrizione (SEO)')
                            ->maxLength(255)
                            ->rows(3),
                    ])->collapsible(),
            ])->columnSpan(['lg' => 1]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            // Dieci righe per volta nascondevano le pagine legali in quarta
            // pagina: il sito ne ha una quarantina, stanno tutte in una.
            ->paginationPageOptions([50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.title')
                    ->label('Genitore')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof PostStatus ? $state : PostStatus::tryFrom((string) $state)) {
                        PostStatus::Published => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(PostStatus::class),
                SelectFilter::make('template')
                    ->label('Sezione (Template)')
                    ->options(PageTemplate::opzioni()),
            ])
            ->recordActions([
                Action::make('Anteprima')
                    ->url(fn ($record) => url($record->slug ?? ''))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
                EditAction::make(),
            ])
            ->toolbarActions(static::standardBulkActions());
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
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['parent', 'author', 'media']);
    }
}
