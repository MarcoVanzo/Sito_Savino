<?php

namespace App\Filament\Resources;

use App\Filament\Actions\RevocaRiconoscimentoVoltiAction;
use App\Filament\Actions\TrainAiFacesAction;
use App\Filament\Resources\PlayerResource\Pages\CreatePlayer;
use App\Filament\Resources\PlayerResource\Pages\EditPlayer;
use App\Filament\Resources\PlayerResource\Pages\ListPlayers;
use App\Filament\Resources\PlayerResource\RelationManagers\PlayerHonoursRelationManager;
use App\Filament\Resources\PlayerResource\RelationManagers\PlayerStatsRelationManager;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Player;
use App\Services\FacialRecognitionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlayerResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Player::class;

    protected static ?string $recordTitleAttribute = 'last_name';

    protected static ?string $modelLabel = 'Anagrafica Atleta';

    protected static ?string $pluralModelLabel = 'Anagrafica Atleti';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|\UnitEnum|null $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 7;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati Anagrafici')
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Cognome')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('date_of_birth')
                            ->label('Data di Nascita'),
                        TextInput::make('nationality')
                            ->label('Nazionalità')
                            ->maxLength(255),
                    ])->columns(2),
                Section::make('Informazioni Aggiuntive')
                    ->schema([
                        TextInput::make('instagram_handle')
                            ->label('Profilo Instagram')
                            ->prefix('@')
                            ->helperText('Nome utente, oppure il link del profilo: si tiene solo il nome. Compare nella scheda dell\'atleta in Stagione.')
                            ->dehydrateStateUsing(fn (?string $state): ?string => Player::instagramHandleDa($state) ?? (filled($state) ? $state : null))
                            ->maxLength(255),
                        TextInput::make('lega_volley_id')
                            ->label('ID Lega Volley')
                            ->numeric()
                            ->unique(ignoreRecord: true)
                            ->helperText('Identificativo sul sito della Lega (se applicabile)'),
                    ])->columns(2),
                Section::make('Foto Profilo')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->label('Immagine di Profilo (Avatar)')
                            ->collection('players')
                            ->image()
                            ->maxSize(5120)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('1200')
                            ->imageResizeUpscale(false)
                            ->helperText('Immagine ottimale: quadrata (es. 800x800px).')
                            ->columnSpanFull(),
                        TextInput::make('testo_alternativo')
                            ->label('Testo alternativo della foto')
                            ->helperText('Vale anche per la foto ufficiale in rosa. Se resta vuoto: «Nome Cognome, Savino Del Bene Volley» (per il vivaio, senza nome).')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('avatar')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->label('')
                    ->collection('players')
                    ->circular(),
                TextColumn::make('first_name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Cognome')
                    ->searchable(),
                TextColumn::make('nationality')
                    ->label('Nazionalità'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('addTrainingFaces')
                        ->label('Addestra AI (Upload Foto)')
                        ->icon('heroicon-o-academic-cap')
                        ->color('success')
                        ->schema(TrainAiFacesAction::formSchema())
                        ->modalHeading('Addestra Intelligenza Artificiale')
                        ->modalDescription('L\'AI imparerà a riconoscere questa atleta analizzando le foto caricate. Questo processo non cancellerà le foto precedenti.')
                        ->modalSubmitActionLabel('Invia e Addestra')
                        ->action(function (Player $record, array $data) {
                            TrainAiFacesAction::execute($record, $data);
                        }),

                    Action::make('resetAiFaces')
                        ->label('Resetta Memoria Volto')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Resetta Volti da CompreFace')
                        ->modalDescription('Attenzione: L\'AI dimenticherà completamente come riconoscere questa atleta. Dovrai addestrarla di nuovo. Procedere?')
                        ->action(function (Player $record) {
                            $service = app(FacialRecognitionService::class);
                            if ($service->deleteAllSubjectExamples($record)) {
                                Notification::make()
                                    ->title('Memoria Azzerata')
                                    ->body('L\'AI non riconoscerà più questa atleta fino al prossimo addestramento.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Errore API')
                                    ->body('Impossibile azzerare la memoria.')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    RevocaRiconoscimentoVoltiAction::perLaTabella(),
                ])->icon('heroicon-m-sparkles')->label('Azioni AI'),
                EditAction::make(),
            ])
            ->toolbarActions(static::softDeleteBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            PlayerHonoursRelationManager::class,
            PlayerStatsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlayers::route('/'),
            'create' => CreatePlayer::route('/create'),
            'edit' => EditPlayer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])->with('media');
    }
}
