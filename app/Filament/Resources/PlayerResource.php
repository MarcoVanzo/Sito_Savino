<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerResource\Pages;
use App\Filament\Resources\PlayerResource\RelationManagers;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Player;
use App\Services\FacialRecognitionService;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlayerResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?string $model = Player::class;

    protected static ?string $recordTitleAttribute = 'last_name';

    protected static ?string $modelLabel = 'Anagrafica Atleta';

    protected static ?string $pluralModelLabel = 'Anagrafica Atleti';

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 7;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dati Anagrafici')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->label('Cognome')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Data di Nascita'),
                        Forms\Components\TextInput::make('nationality')
                            ->label('Nazionalità')
                            ->maxLength(255),
                    ])->columns(2),
                Forms\Components\Section::make('Informazioni Aggiuntive')
                    ->schema([
                        Forms\Components\TextInput::make('instagram_handle')
                            ->label('Profilo Instagram')
                            ->prefix('@')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('lega_volley_id')
                            ->label('ID Lega Volley')
                            ->numeric()
                            ->unique(ignoreRecord: true)
                            ->helperText('Identificativo sul sito della Lega (se applicabile)'),
                    ])->columns(2),
                Forms\Components\Section::make('Foto Profilo')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->label('Immagine di Profilo (Avatar)')
                            ->collection('players')
                            ->image()
                            ->helperText('Immagine ottimale: quadrata (es. 800x800px).')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('avatar')
                    ->label('')
                    ->collection('players')
                    ->circular(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Cognome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nationality')
                    ->label('Nazionalità'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('syncFace')
                    ->label('Sincronizza Volto AI')
                    ->icon('heroicon-o-face-smile')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sincronizza Volto con AI')
                    ->modalDescription('Invia la foto profilo attuale al sistema di riconoscimento facciale per addestrare l\'AI a riconoscere questa atleta.')
                    ->action(function (Player $record) {
                        $media = $record->getFirstMedia('players');
                        if (! $media) {
                            Notification::make()
                                ->title('Errore')
                                ->body('Nessuna immagine di profilo trovata per l\'addestramento.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $service = app(FacialRecognitionService::class);
                        $success = $service->addFaceExample($record, $media->getPath());

                        if ($success) {
                            Notification::make()
                                ->title('Successo')
                                ->body('Volto sincronizzato correttamente con l\'AI.')
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions(static::softDeleteBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PlayerStatsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayers::route('/'),
            'create' => Pages\CreatePlayer::route('/create'),
            'edit' => Pages\EditPlayer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
