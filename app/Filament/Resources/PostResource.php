<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Support\Str;
use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Notizia / Articolo';
    protected static ?string $pluralModelLabel = 'Notizie / Articoli';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Sito Web';
    protected static ?int $navigationSort = 2;

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

                Forms\Components\Section::make('Media e Categorie')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Immagine di Copertina')
                            ->collection('cover')
                            ->image()
                            ->maxSize(5120),
                        Forms\Components\Select::make('categories')
                            ->label('Categorie')
                            ->multiple()
                            ->relationship('categories', 'name')
                            ->preload(),
                        Forms\Components\Select::make('tags')
                            ->label('Tag')
                            ->multiple()
                            ->relationship('tags', 'name')
                            ->preload(),
                    ]),

                Forms\Components\Section::make('Pubblicazione e SEO')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Stato Pubblicazione')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Published)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // Auto-imposta published_at quando si pubblica
                                if ($state === PostStatus::Published->value && ! $get('published_at')) {
                                    $set('published_at', now()->format('Y-m-d H:i:s'));
                                }
                            }),
                        Forms\Components\Select::make('author_id')
                            ->label('Autore')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->default(fn () => auth()->id()),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Data e Ora Pubblicazione'),
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta Titolo (SEO)')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('meta_description')
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
                Tables\Columns\SpatieMediaLibraryImageColumn::make('cover')
                    ->label('')
                    ->collection('cover')
                    ->circular(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'draft' => 'gray',
                        'publish' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Data Pubblicazione')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Autore')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options(PostStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('Anteprima')
                    ->url(fn ($record) => url('news/' . ($record->slug ?? '')))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['author', 'categories']);
    }
}
