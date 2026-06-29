<?php

namespace App\Filament\Resources;

use App\Enums\PostStatus;
use App\Filament\Resources\PageResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    use Translatable;

use HasStandardTableActions;

    protected static ?string $model = Page::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Pagina';

    protected static ?string $pluralModelLabel = 'Pagine';

    protected static ?string $navigationIcon = 'heroicon-o-document';

    protected static ?string $navigationGroup = 'Pagine & Extra';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Contenuto Principale')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Titolo')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        Forms\Components\TextInput::make('slug')
                            ->label('URL Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\RichEditor::make('content')
                            ->label('Contenuto')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('excerpt')
                            ->label('Riassunto (Excerpt)')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Impostazioni e SEO')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Immagine di Copertina')
                            ->collection('cover')
                            ->image()
                            ->maxSize(5120),
                        Forms\Components\Select::make('parent_id')
                            ->relationship('parent', 'title', fn (Builder $query, $record) => $record ? $query->where('id', '!=', $record->id) : $query
                            )
                            ->label('Pagina Genitore')
                            ->searchable(),
                        Forms\Components\Select::make('status')
                            ->label('Stato Pubblicazione')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Published)
                            ->required(),
                        Forms\Components\Select::make('author_id')
                            ->label('Autore')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->default(fn () => auth()->id()),
                        Forms\Components\Select::make('template')
                            ->label('Template Pagina')
                            ->options([
                                'Default' => 'Template Predefinito',
                                'Public/Home' => 'Home Page',
                                'Public/Societa' => 'Società',
                                'Public/Roster' => 'Roster',
                                'Public/Shop' => 'Shop',
                                'Public/Ticketing' => 'Biglietteria',
                                'Public/Sponsor' => 'Sponsor',
                                'Public/Youth' => 'Settore Giovanile',
                                'Public/SummerCamp' => 'Summer Camp',
                                'Public/Sociale' => 'Progetti Sociali',
                                'Public/Comunicazione' => 'Comunicazione',
                                'Public/Stagione' => 'Stagione',
                                'Public/ContentPage' => 'Pagina Contenuto',
                            ])
                            ->live()
                            ->nullable(),
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta Titolo (SEO)')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta Descrizione (SEO)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Group::make([
                            // FORM: SOCIETA
                            Forms\Components\Section::make('Impostazioni Pagina Società')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.hero_subheading')->label('Sottotitolo Hero')->default('Dal 1982'),
                                    Forms\Components\Textarea::make('content_data.hero_description')->label('Descrizione Hero'),
                                    Forms\Components\TextInput::make('content_data.storia_title')->label('Titolo Storia'),
                                    Forms\Components\TagsInput::make('content_data.storia_paragraphs')->label('Paragrafi Storia (Premi Invio per separare)'),
                                    Forms\Components\TextInput::make('content_data.storia_years')->label('Anni di Storia'),
                                    Forms\Components\TextInput::make('content_data.org_title')->label('Titolo Organigramma'),
                                    Forms\Components\TextInput::make('content_data.palazzetto_title')->label('Nome Palazzetto'),
                                    Forms\Components\Textarea::make('content_data.palazzetto_description')->label('Descrizione Palazzetto'),
                                    Forms\Components\TextInput::make('content_data.palazzetto_capacity')->label('Capienza Palazzetto'),
                                    Forms\Components\TextInput::make('content_data.palazzetto_homologation')->label('Omologazione Palazzetto'),
                                    Forms\Components\TextInput::make('content_data.palazzetto_address')->label('Indirizzo Palazzetto'),
                                ])
                                ->visible(fn (Forms\Get $get) => $get('template') === 'Public/Societa'),

                            // FORM: TICKETING
                            Forms\Components\Section::make('Impostazioni Pagina Biglietteria')
                                ->schema([
                                    Forms\Components\TextInput::make('content_data.hero_label')->label('Etichetta Hero'),
                                    Forms\Components\Textarea::make('content_data.hero_subtitle')->label('Sottotitolo Hero'),
                                    Forms\Components\TextInput::make('content_data.plans_heading')->label('Titolo Sezione Abbonamenti'),
                                    Forms\Components\TextInput::make('content_data.popular_badge')->label('Testo Badge "Più Popolare"'),
                                    Forms\Components\Repeater::make('content_data.plans')
                                        ->label('Piani e Abbonamenti')
                                        ->schema([
                                            Forms\Components\TextInput::make('name')->label('Nome Piano')->required(),
                                            Forms\Components\TextInput::make('price')->label('Prezzo (€)')->required(),
                                            Forms\Components\TextInput::make('period')->label('Periodo (es. a partita, stagione)')->required(),
                                            Forms\Components\TagsInput::make('features')->label('Vantaggi (Premi invio)'),
                                            Forms\Components\Toggle::make('highlight')->label('Evidenziato (Più Popolare)'),
                                            Forms\Components\TextInput::make('cta')->label('Testo Pulsante (es. Acquista)'),
                                        ])->columns(2)->columnSpanFull(),
                                    Forms\Components\TextInput::make('content_data.info_heading')->label('Titolo Sezione Info'),
                                    Forms\Components\TextInput::make('content_data.online_title')->label('Titolo Info Online'),
                                    Forms\Components\Textarea::make('content_data.online_description')->label('Descrizione Info Online'),
                                    Forms\Components\TextInput::make('content_data.boxoffice_title')->label('Titolo Info Botteghino'),
                                    Forms\Components\Textarea::make('content_data.boxoffice_description')->label('Descrizione Info Botteghino'),
                                ])
                                ->visible(fn (Forms\Get $get) => $get('template') === 'Public/Ticketing'),

                            // GENERIC JSON per altre pagine
                            Forms\Components\Section::make('Dati Contenuto (Altre Pagine)')
                                ->schema([
                                    Forms\Components\KeyValue::make('content_data')
                                        ->label('Variabili Template (Chiave-Valore)')
                                        ->keyLabel('Chiave (es. hero_title)')
                                        ->valueLabel('Valore testuale'),
                                ])
                                ->visible(fn (Forms\Get $get) => !in_array($get('template'), ['Public/Societa', 'Public/Ticketing'])),
                        ])->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.title')
                    ->label('Genitore')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'draft' => 'gray',
                        'publish' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options(PostStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('Anteprima')
                    ->url(fn ($record) => url($record->slug ?? ''))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions(static::standardBulkActions());
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['parent', 'author']);
    }
}
