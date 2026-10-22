<?php

namespace App\Filament\Resources;

use App\Enums\StaffType;
use App\Filament\Resources\ManagementResource\Pages\CreateManagement;
use App\Filament\Resources\ManagementResource\Pages\EditManagement;
use App\Filament\Resources\ManagementResource\Pages\ListManagement;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\StaffMember;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class ManagementResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?string $model = StaffMember::class;

    protected static ?string $recordTitleAttribute = 'last_name';

    protected static ?string $modelLabel = 'Membro Organigramma';

    protected static ?string $pluralModelLabel = 'Membri Organigramma';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Società';

    protected static ?string $navigationLabel = 'Membri Organigramma';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', StaffType::Dirigenza)->with('media');
    }

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
                        // Campo nascosto vero: una Select con `hidden()` non viene
                        // deidratata, e senza `type` (NOT NULL, nessun default) la
                        // creazione di un membro falliva con un errore SQL.
                        Hidden::make('type')
                            ->default(StaffType::Dirigenza->value),
                        TextInput::make('role')
                            ->label('Ruolo specifico')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Es. Allenatore, Presidente, Medico...'),
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
            ])
            ->recordActions([
                EditAction::make(),
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
            'index' => ListManagement::route('/'),
            'create' => CreateManagement::route('/create'),
            'edit' => EditManagement::route('/{record}/edit'),
        ];
    }
}
