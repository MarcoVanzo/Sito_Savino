<?php

namespace App\Filament\Resources\GalleryEventResource\RelationManagers;

use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryImage;
use App\Models\Player;
use App\Models\StaffMember;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GalleryImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'galleryImages';

    protected static ?string $title = 'Foto';

    protected static ?string $modelLabel = 'Foto';

    protected static ?string $pluralModelLabel = 'Foto';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Titolo')
                    ->maxLength(255),
                Select::make('players')
                    ->label('Atlete presenti')
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
                    ->label('Staff presente')
                    ->multiple()
                    ->options(fn () => StaffMember::orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (StaffMember $s) => [$s->id => "{$s->first_name} {$s->last_name} ({$s->role})"]))
                    ->searchable()
                    ->preload()
                    ->afterStateHydrated(function (Select $component, ?GalleryImage $record) {
                        if ($record) {
                            $component->state($record->staffMembers->pluck('id')->toArray());
                        }
                    }),
                Toggle::make('is_active')
                    ->label('Attiva')
                    ->default(true),
                Toggle::make('needs_review')
                    ->label('Da revisionare'),
                SpatieMediaLibraryFileUpload::make('gallery')
                    ->collection('gallery')
                    ->disk('local')
                    ->image()
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth('2400')
                    ->imageResizeTargetHeight('2400')
                    ->imageResizeUpscale(false)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        // Aggiunto caricamento media e players per performance
        $table->modifyQueryUsing(fn (Builder $query) => $query->with(['media', 'players', 'staffMembers']));

        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('gallery')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->collection('gallery')
                    ->label('Foto')
                    ->circular(false)
                    ->width(80)
                    ->height(60),
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('players.last_name')
                    ->label('Atlete')
                    ->badge()
                    ->separator(', '),
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
                TernaryFilter::make('needs_review')
                    ->label('Da Revisionare')
                    ->trueLabel('Sì, volto quasi riconosciuto')
                    ->falseLabel('No, OK'),
            ])
            ->headerActions([
                Action::make('analyzeAll')
                    ->label('Analizza tutte con AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Analizza tutte le foto')
                    ->modalDescription('Verranno analizzate tutte le foto di questo evento con l\'Intelligenza Artificiale. L\'operazione avviene in background.')
                    ->action(function () {
                        $images = $this->getOwnerRecord()
                            ->galleryImages()
                            ->whereHas('media')
                            ->whereNull('ai_analyzed_at')
                            ->get();

                        if ($images->isEmpty()) {
                            Notification::make()
                                ->title('Tutte le foto sono già analizzate')
                                ->body('Non ci sono nuove foto da analizzare per questo evento.')
                                ->warning()
                                ->send();

                            return;
                        }

                        foreach ($images as $image) {
                            AnalyzeGalleryImageJob::dispatch($image);
                        }
                        Notification::make()
                            ->title('Analisi AI avviata')
                            ->body($images->count().' nuove foto in fase di analisi in background.')
                            ->success()
                            ->send();
                    }),
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
                        Notification::make()->title('Identificata')->success()->send();
                    }),
                EditAction::make()
                    ->after(function (GalleryImage $record, array $data) {
                        $record->players()->sync($data['players'] ?? []);
                        $record->staffMembers()->sync($data['staff_members'] ?? []);
                    }),
                DeleteAction::make()
                    ->before(function (GalleryImage $record) {
                        $record->clearMediaCollection('gallery');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records) {
                            foreach ($records as $record) {
                                $record->clearMediaCollection('gallery');
                            }
                        }),
                    BulkAction::make('analyzeBulk')
                        ->label('Analizza con AI')
                        ->icon('heroicon-o-sparkles')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                if ($record instanceof GalleryImage) {
                                    AnalyzeGalleryImageJob::dispatch($record);
                                }
                            }
                            Notification::make()
                                ->title('Analisi avviata')
                                ->body($records->count().' foto in fase di analisi.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
    }
}
