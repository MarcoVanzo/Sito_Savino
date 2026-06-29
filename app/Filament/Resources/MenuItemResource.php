<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $modelLabel = 'Voce Menu';

    protected static ?string $pluralModelLabel = 'Voci Menu';

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = 'Pagine & Extra';

    protected static ?int $navigationSort = 46;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Contenuto')
                    ->schema([
                        Forms\Components\TextInput::make('label')
                            ->label('Etichetta')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('url')
                            ->label('URL (lascia vuoto per "Pagina in costruzione")')
                            ->nullable()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('motto_title')
                            ->label('Titolo Motto')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('motto_subtitle')
                            ->label('Sottotitolo Motto')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Gerarchia e Posizione')
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->label('Voce Genitore')
                            ->relationship('parent', 'label', fn (Builder $query, $record) => $record ? $query->where('id', '!=', $record->id) : $query
                            )
                            ->searchable()
                            ->nullable(),
                        Forms\Components\Select::make('location')
                            ->label('Posizione')
                            ->options([
                                'main' => 'Menu Principale',
                                'footer' => 'Footer',
                            ])
                            ->default('main')
                            ->required(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Ordine')
                            ->numeric()
                            ->default(0),
                    ])->columns(3),

                Forms\Components\Section::make('Impostazioni')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true),
                        Forms\Components\Toggle::make('is_highlight')
                            ->label('Evidenziata')
                            ->default(false),
                    ])->columns(2),

                Forms\Components\Section::make('Immagine')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->label('Immagine Menu')
                            ->collection('menu-images')
                            ->image()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Etichetta')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(40),
                Tables\Columns\TextColumn::make('parent.label')
                    ->label('Genitore')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->label('Posizione')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'main' => 'primary',
                        'footer' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Attiva'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->label('Posizione')
                    ->options([
                        'main' => 'Menu Principale',
                        'footer' => 'Footer',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['parent']);
    }
}
