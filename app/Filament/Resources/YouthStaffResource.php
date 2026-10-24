<?php

namespace App\Filament\Resources;

use App\Enums\StaffType;
use App\Filament\Clusters\SdbYouth;
use App\Filament\Resources\YouthStaffResource\Pages\CreateYouthStaff;
use App\Filament\Resources\YouthStaffResource\Pages\EditYouthStaff;
use App\Filament\Resources\YouthStaffResource\Pages\ListYouthStaff;
use App\Filament\Traits\HasStandardTableActions;
use App\Filament\Traits\SchedaDelloStaff;
use App\Models\StaffMember;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class YouthStaffResource extends Resource
{
    use HasStandardTableActions;
    use SchedaDelloStaff;
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
                self::anagraficaDelloStaff('youth'),

                self::fotoDelProfilo(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(self::colonneDelloStaff())
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
