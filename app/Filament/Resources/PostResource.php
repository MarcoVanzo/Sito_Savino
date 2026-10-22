<?php

namespace App\Filament\Resources;

use App\Enums\PostStatus;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class PostResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?string $model = Post::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Notizia / Articolo';

    protected static ?string $pluralModelLabel = 'Notizie / Articoli';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'News & Comunicati';

    protected static string|\UnitEnum|null $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'news';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ])->columns(2),

                Section::make('Media e Categorie')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Immagine di Copertina')
                            ->collection('cover')
                            ->image()
                            ->maxSize(5120)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1400')
                            ->imageResizeTargetHeight('1400')
                            ->imageResizeUpscale(false),
                        TextInput::make('testo_alternativo')
                            ->label('Testo alternativo della copertina')
                            ->helperText('Cosa mostra la foto, per chi non vede le immagini e per i motori di ricerca. Se resta vuoto si usa il titolo della notizia.')
                            ->maxLength(255),
                        Select::make('categories')
                            ->label('Categorie')
                            ->multiple()
                            ->relationship('categories', 'name')
                            ->preload(),
                        Select::make('tags')
                            ->label('Tag')
                            ->multiple()
                            ->relationship('tags', 'name')
                            ->preload(),
                    ]),

                Section::make('Pubblicazione e SEO')
                    ->schema([
                        Select::make('status')
                            ->label('Stato Pubblicazione')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Draft)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                // Auto-imposta published_at quando si pubblica
                                if ($state === PostStatus::Published->value && ! $get('published_at')) {
                                    $set('published_at', now()->format('Y-m-d H:i:s'));
                                }
                            }),
                        Select::make('author_id')
                            ->label('Autore')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->default(fn () => auth()->id()),
                        DateTimePicker::make('published_at')
                            ->label('Data e Ora Pubblicazione'),
                        TextInput::make('meta_title')
                            ->label('Meta Titolo (SEO)')
                            ->maxLength(255),
                        Textarea::make('meta_description')
                            ->label('Meta Descrizione (SEO)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->label('')
                    ->collection('cover')
                    ->circular(),
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof PostStatus ? $state : PostStatus::tryFrom((string) $state)) {
                        PostStatus::Published => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('published_at')
                    ->label('Data Pubblicazione')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label('Autore')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(PostStatus::class),
            ])
            ->recordActions([
                Action::make('Anteprima')
                    ->url(fn ($record) => url('news/'.($record->slug ?? '')))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
                ViewAction::make(),
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
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['author', 'categories', 'media']);
    }
}
