<?php

namespace App\Filament\Resources;

use App\Enums\StaffType;
use App\Filament\Clusters\SdbYouth;
use App\Filament\Resources\YouthStaffResource\Pages\CreateYouthStaff;
use App\Filament\Resources\YouthStaffResource\Pages\EditYouthStaff;
use App\Filament\Resources\YouthStaffResource\Pages\ListYouthStaff;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\StaffMember;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
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

class YouthStaffResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $model = StaffMember::class;

    protected static ?string $recordTitleAttribute = 'last_name';

    protected static ?string $modelLabel = 'Membro Staff Youth';

    protected static ?string $pluralModelLabel = 'Staff Youth';

    protected static ?string $cluster = SdbYouth::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Staff e Medico';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'youth-staff';

    /**
     * Filtra solo staff con section = 'youth'.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('section', 'youth')
            ->whereIn('type', [StaffType::Tecnico, StaffType::Medico])->with('media');
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
                            ->default('youth'),
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
            'index' => ListYouthStaff::route('/'),
            'create' => CreateYouthStaff::route('/create'),
            'edit' => EditYouthStaff::route('/{record}/edit'),
        ];
    }
}
