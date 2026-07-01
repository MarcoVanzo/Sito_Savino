<?php

namespace App\Filament\Resources;

use App\Enums\PlayerPosition;
use App\Filament\Clusters\SdbYouth;
use App\Filament\Resources\YouthRosterResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Roster;
use App\Services\FacialRecognitionService;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class YouthRosterResource extends Resource
{
    use HasStandardTableActions;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $model = Roster::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Atleta Youth';

    protected static ?string $pluralModelLabel = 'Atlete Youth';

    protected static ?string $cluster = SdbYouth::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'Atlete';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'youth-roster';

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
                            ->relationship(
                                'team',
                                'name',
                                fn (Builder $query) => $query->whereIn('category', ['B1', 'U17', 'U15'])
                            )
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
                    ->conversion('thumb')
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
                Tables\Columns\TextColumn::make('team.category')
                    ->label('Categoria')
                    ->badge()
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
                Tables\Filters\SelectFilter::make('team_category')
                    ->label('Categoria')
                    ->options([
                        'B1' => 'Serie B1',
                        'U17' => 'Serie U17',
                        'U15' => 'Serie U15',
                    ])
                    ->query(fn (Builder $query, array $data) => $data['value']
                        ? $query->whereHas('team', fn ($q) => $q->where('category', $data['value']))
                        : $query
                    ),
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
                        if (! $media) {
                            Notification::make()
                                ->title('Errore')
                                ->body('Nessuna foto trovata (né ufficiale né avatar) per addestrare l\'AI.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $service = app(FacialRecognitionService::class);
                        $success = $service->addFaceExampleFromMedia($record->player, $media);

                        if ($success) {
                            Notification::make()
                                ->title('Successo')
                                ->body('Volto sincronizzato! L\'AI ora riconoscerà questa atleta.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
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
            'index' => Pages\ListYouthRosters::route('/'),
            'create' => Pages\CreateYouthRoster::route('/create'),
            'edit' => Pages\EditYouthRoster::route('/{record}/edit'),
        ];
    }

    /**
     * Filtra solo i roster appartenenti a squadre Youth (B1, U17, U15).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['player', 'team', 'season', 'media'])
            ->whereHas('team', fn (Builder $q) => $q->whereIn('category', ['B1', 'U17', 'U15']));
    }
}
