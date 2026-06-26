<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SponsorResource\Pages;
use App\Models\Sponsor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class SponsorResource extends Resource
{
    protected static ?string $model = Sponsor::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Sponsor';
    protected static ?string $pluralModelLabel = 'Sponsor';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Gestione Sportiva';
    protected static ?int $navigationSort = 11;

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
                    ->options([
                        'main' => 'Main Sponsor',
                        'gold' => 'Gold',
                        'silver' => 'Silver',
                        'technical' => 'Sponsor Tecnico',
                        'standard' => 'Standard',
                    ])
                    ->default('standard')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tier')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'main' => 'warning',
                        'technical' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('url'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier')
                    ->label('Livello')
                    ->options([
                        'main' => 'Main Sponsor',
                        'gold' => 'Gold',
                        'silver' => 'Silver',
                        'technical' => 'Sponsor Tecnico',
                        'standard' => 'Standard',
                    ]),
            ])
            ->actions([
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
            'index' => Pages\ListSponsors::route('/'),
            'create' => Pages\CreateSponsor::route('/create'),
            'edit' => Pages\EditSponsor::route('/{record}/edit'),
        ];
    }
}
