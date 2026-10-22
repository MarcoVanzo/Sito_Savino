<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryImageResource\Pages\CreateGalleryImage;
use App\Filament\Resources\GalleryImageResource\Pages\EditGalleryImage;
use App\Filament\Resources\GalleryImageResource\Pages\ListGalleryImages;
use App\Filament\Traits\HasStandardTableActions;
use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryImage;
use App\Models\Player;
use App\Models\StaffMember;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class GalleryImageResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = GalleryImage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Immagine Galleria';

    protected static ?string $pluralModelLabel = 'Galleria Fotografica';

    protected static string|\UnitEnum|null $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'gallery-images';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Immagine e Dettagli')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->label('Immagine')
                            ->collection('gallery')
                            ->image()
                            ->disk('local')
                            ->maxSize(51200)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('2400')
                            ->imageResizeTargetHeight('2400')
                            ->imageResizeUpscale(false)
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('title')
                            ->label('Titolo / Testo Alternativo')
                            ->maxLength(255)
                            ->helperText('Breve descrizione dell\'immagine, utile per SEO e accessibilità.'),
                        TextInput::make('category')
                            ->label('Categoria')
                            ->required()
                            ->maxLength(255)
                            ->default('Partite')
                            ->datalist([
                                'Partite',
                                'Allenamenti',
                                'Eventi',
                                'Tifosi',
                                'Backstage',
                            ]),
                        Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true)
                            ->required(),
                        Select::make('players')
                            ->label('Atlete Taggate')
                            ->multiple()
                            ->options(fn () => Player::orderBy('last_name')
                                ->get()
                                ->mapWithKeys(fn (Player $p) => [$p->id => "{$p->first_name} {$p->last_name}"]))
                            ->searchable()
                            ->preload()
                            ->afterStateHydrated(function (Select $component, ?GalleryImage $record) {
                                if ($record) {
                                    $component->state($record->players->pluck('id')->toArray());
                                }
                            }),
                        Select::make('staff_members')
                            ->label('Staff Taggato')
                            ->multiple()
                            ->options(fn () => StaffMember::orderBy('last_name')
                                ->get()
                                ->mapWithKeys(fn (StaffMember $s) => [$s->id => "{$s->first_name} {$s->last_name}"]))
                            ->searchable()
                            ->preload()
                            ->afterStateHydrated(function (Select $component, ?GalleryImage $record) {
                                if ($record) {
                                    $component->state($record->staffMembers->pluck('id')->toArray());
                                }
                            }),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->label('Immagine')
                    ->collection('gallery')
                    ->square(),
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                TextColumn::make('players.last_name')
                    ->label('Atlete')
                    ->badge()
                    ->searchable()
                    ->limitList(3),
                IconColumn::make('needs_review')
                    ->label('Da Rev.')
                    ->tooltip('Un volto grande somiglia a una persona in anagrafica senza raggiungere la soglia del tag: conferma o correggi con "Identifica".')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
                IconColumn::make('is_active')
                    ->label('Attiva')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(function () {
                        return Cache::remember('gallery_image_categories', 3600, fn () => GalleryImage::query()
                            ->select('category')
                            ->distinct()
                            ->pluck('category', 'category')
                            ->toArray()
                        );
                    }),
                SelectFilter::make('gallery_event_id')
                    ->label('Evento')
                    ->relationship('galleryEvent', 'title')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('needs_review')
                    ->label('Da Revisionare')
                    ->placeholder('Tutte le foto')
                    ->trueLabel('Sì, volto quasi riconosciuto')
                    ->falseLabel('No, OK'),
            ])
            ->recordActions([
                Action::make('identifica')
                    ->label('Identifica')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->visible(fn (GalleryImage $record) => $record->needs_review)
                    ->mountUsing(function (Schema $schema, GalleryImage $record) {
                        $schema->fill([
                            'players' => $record->players->pluck('id')->toArray(),
                            'staff_members' => $record->staffMembers->pluck('id')->toArray(),
                        ]);
                    })
                    ->schema([
                        Select::make('players')
                            ->label('Atlete presenti')
                            ->multiple()
                            ->options(fn () => Player::orderBy('last_name')
                                ->get()
                                ->mapWithKeys(fn (Player $p) => [$p->id => "{$p->first_name} {$p->last_name}"]))
                            ->searchable(),
                        Select::make('staff_members')
                            ->label('Staff presente')
                            ->multiple()
                            ->options(fn () => StaffMember::orderBy('last_name')
                                ->get()
                                ->mapWithKeys(fn (StaffMember $s) => [$s->id => "{$s->first_name} {$s->last_name} ({$s->role})"]))
                            ->searchable(),
                    ])
                    ->action(function (GalleryImage $record, array $data) {
                        $record->players()->sync($data['players'] ?? []);
                        $record->staffMembers()->sync($data['staff_members'] ?? []);
                        $record->needs_review = false;
                        $record->save();
                        Notification::make()->title('Identificata con successo')->success()->send();
                    }),
                EditAction::make()
                    ->after(function (GalleryImage $record, array $data) {
                        $record->players()->sync($data['players'] ?? []);
                        $record->staffMembers()->sync($data['staff_members'] ?? []);
                    }),
                Action::make('analyze')
                    ->label('Analizza AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->action(function (GalleryImage $record) {
                        AnalyzeGalleryImageJob::dispatch($record);
                        Notification::make()
                            ->title('Analisi AI avviata')
                            ->body('La foto verrà analizzata in background.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->before(function (GalleryImage $record) {
                        $record->clearMediaCollection('gallery');
                    }),
            ])
            ->toolbarActions(array_merge(static::standardBulkActions(), [
                BulkAction::make('analyzeBulk')
                    ->label('Analizza con AI (Bulk)')
                    ->icon('heroicon-o-sparkles')
                    ->action(function (Collection $records) {
                        foreach ($records as $record) {
                            if ($record instanceof GalleryImage) {
                                AnalyzeGalleryImageJob::dispatch($record);
                            }
                        }
                        Notification::make()
                            ->title('Analisi avviata')
                            ->body('Le foto selezionate verranno analizzate in background.')
                            ->success()
                            ->send();
                    }),
            ]))
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGalleryImages::route('/'),
            'create' => CreateGalleryImage::route('/create'),
            'edit' => EditGalleryImage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['media', 'players', 'staffMembers']);
    }
}
