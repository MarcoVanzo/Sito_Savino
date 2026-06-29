<?php

namespace App\Filament\Resources;

use App\Enums\SponsorTier;
use App\Filament\Resources\SponsorResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Sponsor;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables;
use Filament\Tables\Table;

class SponsorResource extends Resource
{
    use Translatable;

use HasStandardTableActions;

    protected static ?string $model = Sponsor::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Sponsor';

    protected static ?string $pluralModelLabel = 'Sponsor';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Gestione Sponsor';

    protected static ?string $navigationGroup = 'Sponsor';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'sponsor';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('url')
                    ->url()
                    ->maxLength(255),
                Forms\Components\Select::make('tier')
                    ->options(SponsorTier::class)
                    ->default(SponsorTier::Standard)
                    ->required(),
                SpatieMediaLibraryFileUpload::make('logo')
                    ->collection('sponsors')
                    ->image()
                    ->maxSize(2048)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('logo')
                    ->label('Logo')
                    ->collection('sponsors')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tier')
                    ->label('Livello')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        SponsorTier::Main => 'warning',
                        SponsorTier::Gold => 'warning',
                        SponsorTier::Silver => 'gray',
                        SponsorTier::Technical => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('url')
                    ->label('Sito Web'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier')
                    ->label('Livello')
                    ->options(SponsorTier::class),
            ])
            ->actions(static::viewAndEditActions())
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
            'index' => Pages\ListSponsors::route('/'),
            'create' => Pages\CreateSponsor::route('/create'),
            'edit' => Pages\EditSponsor::route('/{record}/edit'),
        ];
    }
}
