<?php

namespace App\Filament\Resources;

use App\Enums\StaffType;
use App\Filament\Actions\RevocaRiconoscimentoVoltiAction;
use App\Filament\Actions\TrainAiFacesAction;
use App\Filament\Clusters\SerieA1;
use App\Filament\Resources\StaffMemberResource\Pages\CreateStaffMember;
use App\Filament\Resources\StaffMemberResource\Pages\EditStaffMember;
use App\Filament\Resources\StaffMemberResource\Pages\ListStaffMembers;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\StaffMember;
use App\Services\FacialRecognitionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class StaffMemberResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $model = StaffMember::class;

    protected static ?string $recordTitleAttribute = 'last_name';

    protected static ?string $modelLabel = 'Membro Staff/Medico';

    protected static ?string $pluralModelLabel = 'Staff Tecnico e Medico';

    protected static ?string $cluster = SerieA1::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Staff e Medico';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($q) {
                $q->where('section', 'a1')->orWhereNull('section');
            })
            ->whereIn('type', [StaffType::Tecnico, StaffType::Medico])
            ->with('media');
    }

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Anagrafica e Ruolo')
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Cognome')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->label('Sezione (Tipologia)')
                            ->options([
                                StaffType::Tecnico->value => StaffType::Tecnico->label(),
                                StaffType::Medico->value => StaffType::Medico->label(),
                            ])
                            ->required()
                            ->helperText('Determina in quale pagina del sito comparirà.'),
                        TextInput::make('role')
                            ->label('Ruolo specifico')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Es. Allenatore, Presidente, Medico...'),
                        Hidden::make('section')
                            ->default('a1'),
                    ])->columns(2),

                Section::make('Foto Profilo')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->label('Immagine di Profilo (Avatar)')
                            ->collection('staff')
                            ->image()
                            ->maxSize(5120)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('1200')
                            ->imageResizeUpscale(false)
                            ->helperText('Immagine ottimale: quadrata (es. 800x800px).')
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
                    ->collection('staff')
                    ->circular(),
                TextColumn::make('first_name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Cognome')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Ruolo')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Sezione')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('type')
                    ->label('Filtra per Sezione')
                    ->options([
                        StaffType::Tecnico->value => StaffType::Tecnico->label(),
                        StaffType::Medico->value => StaffType::Medico->label(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('trainAi')
                        ->label('Addestra AI (Upload Foto)')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('info')
                        ->modalHeading('Addestramento Riconoscimento Facciale')
                        ->modalDescription('Carica foto del volto di questa persona. L\'AI imparerà a riconoscerla nelle foto della gallery.')
                        ->schema(TrainAiFacesAction::formSchema())
                        ->action(function (StaffMember $record, array $data) {
                            TrainAiFacesAction::execute($record, $data);
                        }),
                    Action::make('resetAi')
                        ->label('Resetta Memoria Volto')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Resetta Memoria AI')
                        ->modalDescription('L\'AI dimenticherà come riconoscere questa persona. Sarà necessario ri-addestrarla con nuove foto.')
                        ->action(function (StaffMember $record) {
                            $service = app(FacialRecognitionService::class);
                            $service->deleteAllSubjectExamples($record);
                            Notification::make()
                                ->title('Memoria AI resettata')
                                ->body('Il volto è stato rimosso dalla memoria AI.')
                                ->success()
                                ->send();
                        }),

                    RevocaRiconoscimentoVoltiAction::perLaTabella(),
                ])
                    ->label('Azioni AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('info'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffMembers::route('/'),
            'create' => CreateStaffMember::route('/create'),
            'edit' => EditStaffMember::route('/{record}/edit'),
        ];
    }
}
