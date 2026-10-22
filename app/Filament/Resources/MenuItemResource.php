<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItemResource\Pages\EditMenuItem;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Models\MenuItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class MenuItemResource extends Resource
{
    use Translatable;

    protected static ?string $model = MenuItem::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $modelLabel = 'Voce Menu';

    protected static ?string $pluralModelLabel = 'Voci Menu';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static string|\UnitEnum|null $navigationGroup = 'Pagine & Extra';

    protected static ?int $navigationSort = 46;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenuto')
                    ->schema([
                        TextInput::make('label')
                            ->label('Etichetta')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('url')
                            ->label('URL (lascia vuoto per "Pagina in costruzione")')
                            ->nullable()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(3)
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('motto_title')
                            ->label('Titolo Motto')
                            ->maxLength(255),
                        TextInput::make('motto_subtitle')
                            ->label('Sottotitolo Motto')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Gerarchia e Posizione')
                    ->schema([
                        Select::make('parent_id')
                            ->label('Voce Genitore')
                            ->relationship('parent', 'label', fn (Builder $query, $record) => $record ? $query->where('id', '!=', $record->id) : $query
                            )
                            ->searchable()
                            ->nullable(),
                        Select::make('location')
                            ->label('Posizione')
                            ->options([
                                'main' => 'Menu Principale',
                                'footer' => 'Footer',
                            ])
                            ->default('main')
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Ordine')
                            ->numeric()
                            ->default(0),
                    ])->columns(3),

                Section::make('Impostazioni')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true),
                        Toggle::make('is_highlight')
                            ->label('Evidenziata')
                            ->helperText('La voce esce dalla barra del menu e diventa il pulsante pieno in testata (oggi lo Shop). Una sola voce per volta.')
                            ->default(false),
                    ])->columns(2),

                Section::make('Immagine')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->label('Immagine Menu')
                            ->collection('menu-images')
                            ->image()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                        // Il riquadro e' piu' stretto della foto, quindi qualcosa si
                        // taglia sempre: qui si sceglie quale parte tenere.
                        Select::make('menu_image_position')
                            ->label('Parte della foto da tenere')
                            ->helperText('Se il soggetto non sta al centro dello scatto, spostare qui l\'inquadratura.')
                            ->options([
                                'center' => 'Centro',
                                'top' => 'Alto',
                                'bottom' => 'Basso',
                                'left' => 'Sinistra',
                                'right' => 'Destra',
                            ])
                            ->default('center')
                            ->native(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Etichetta')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('url')
                    ->label('URL')
                    ->limit(40),
                TextColumn::make('parent.label')
                    ->label('Genitore')
                    ->sortable(),
                TextColumn::make('location')
                    ->label('Posizione')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'main' => 'primary',
                        'footer' => 'gray',
                        default => 'gray',
                    }),
                ToggleColumn::make('is_active')
                    ->label('Attiva'),
                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('location')
                    ->label('Posizione')
                    ->options([
                        'main' => 'Menu Principale',
                        'footer' => 'Footer',
                    ]),
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
            'index' => ListMenuItems::route('/'),
            'create' => CreateMenuItem::route('/create'),
            'edit' => EditMenuItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['parent', 'media']);
    }
}
