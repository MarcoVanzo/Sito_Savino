<?php

namespace App\Filament\Resources;

use App\Enums\PlayerPosition;
use App\Filament\Resources\RosterResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Roster;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\SubNavigationPosition;

class RosterResource extends Resource
{
    use HasStandardTableActions;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $model = Roster::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Giocatore in Rosa';

    protected static ?string $pluralModelLabel = 'Giocatori in Rosa (Roster)';
    
    protected static ?string $cluster = \App\Filament\Clusters\SerieA1::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'Atlete';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'roster';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Associazione Stagionale')
                    ->schema([
                        Forms\Components\Select::make('player_id')
                            ->label('Atleta')
                            ->relationship('player', 'last_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('team_id')
                            ->label('Squadra')
                            ->relationship('team', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('season_id')
                            ->label('Stagione')
                            ->relationship('season', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(3),
                Forms\Components\Section::make('Dettagli Tecnici')
                    ->schema([
                        Forms\Components\TextInput::make('jersey_number')
                            ->label('Numero di Maglia')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\Select::make('role')
                            ->label('Ruolo in Campo')
                            ->options(PlayerPosition::class)
                            ->required(),
                        Forms\Components\TextInput::make('height_cm')
                            ->label('Altezza (cm)')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\Toggle::make('is_captain')
                            ->label('Capitano della Squadra')
                            ->required(),
                    ])->columns(2),
                Forms\Components\Section::make('Biografia e Foto')
                    ->schema([
                        Forms\Components\Textarea::make('bio')
                            ->label('Biografia Stagionale')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('official_photo')
                            ->label('Foto Ufficiale (Roster)')
                            ->collection('rosters_official')
                            ->image(),
                        SpatieMediaLibraryFileUpload::make('action_photo')
                            ->label('Foto in Azione')
                            ->collection('rosters_action')
                            ->image(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('official_photo')
                    ->label('')
                    ->collection('rosters_official')
                    ->circular()
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('player.last_name')
                    ->label('Atleta')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Squadra')
                    ->sortable(),
                Tables\Columns\TextColumn::make('season.name')
                    ->label('Stagione')
                    ->sortable(),
                Tables\Columns\TextColumn::make('jersey_number')
                    ->label('Maglia'),
                Tables\Columns\TextColumn::make('role')
                    ->label('Ruolo'),
                Tables\Columns\IconColumn::make('is_captain')
                    ->label('Capitano')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name'),
            ])
            ->actions(array_merge([
                Tables\Actions\Action::make('syncFace')
                    ->label('Addestra AI (Sincronizza Volto)')
                    ->icon('heroicon-o-face-smile')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sincronizza Volto con AI')
                    ->modalDescription('Invia la foto ufficiale di questa atleta al sistema di riconoscimento facciale per addestrare l\'AI a riconoscerla.')
                    ->action(function (Roster $record) {
                        $media = $record->getFirstMedia('rosters_official') ?? $record->player->getFirstMedia('players');
                        if (!$media) {
                            \Filament\Notifications\Notification::make()
                                ->title('Errore')
                                ->body('Nessuna foto trovata (né ufficiale né avatar) per addestrare l\'AI.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $service = app(\App\Services\FacialRecognitionService::class);
                        $success = $service->addFaceExample($record->player, $media->getPath());

                        if ($success) {
                            \Filament\Notifications\Notification::make()
                                ->title('Successo')
                                ->body('Volto sincronizzato! L\'AI ora riconoscerà questa atleta.')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Errore API')
                                ->body('Impossibile sincronizzare il volto. Verifica i log.')
                                ->danger()
                                ->send();
                        }
                    }),
            ], static::viewAndEditActions()))
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
            'index' => Pages\ListRosters::route('/'),
            'create' => Pages\CreateRoster::route('/create'),
            'edit' => Pages\EditRoster::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['player', 'team', 'season', 'media']);
    }
}
