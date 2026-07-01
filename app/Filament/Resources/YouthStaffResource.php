<?php

namespace App\Filament\Resources;

use App\Enums\StaffType;
use App\Filament\Clusters\SdbYouth;
use App\Filament\Resources\YouthStaffResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\StaffMember;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class YouthStaffResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $model = StaffMember::class;

    protected static ?string $recordTitleAttribute = 'last_name';

    protected static ?string $modelLabel = 'Membro Staff Youth';

    protected static ?string $pluralModelLabel = 'Staff Youth';

    protected static ?string $cluster = SdbYouth::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Anagrafica e Ruolo')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->label('Cognome')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->label('Sezione (Tipologia)')
                            ->options([
                                StaffType::Tecnico->value => StaffType::Tecnico->label(),
                                StaffType::Medico->value => StaffType::Medico->label(),
                            ])
                            ->required()
                            ->helperText('Determina in quale pagina del sito comparirà.'),
                        Forms\Components\TextInput::make('role')
                            ->label('Ruolo specifico')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Es. Allenatore, Presidente, Medico...'),
                        Forms\Components\Hidden::make('section')
                            ->default('youth'),
                    ])->columns(2),

                Forms\Components\Section::make('Foto Profilo')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->label('Immagine di Profilo (Avatar)')
                            ->collection('staff')
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
                    ->conversion('thumb')
                    ->label('')
                    ->collection('staff')
                    ->circular(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Cognome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Ruolo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Sezione')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Filtra per Sezione')
                    ->options([
                        StaffType::Tecnico->value => StaffType::Tecnico->label(),
                        StaffType::Medico->value => StaffType::Medico->label(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListYouthStaff::route('/'),
            'create' => Pages\CreateYouthStaff::route('/create'),
            'edit' => Pages\EditYouthStaff::route('/{record}/edit'),
        ];
    }
}
