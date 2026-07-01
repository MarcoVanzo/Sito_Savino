<?php

namespace App\Filament\Resources\GalleryEventResource\RelationManagers;

use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryImage;
use App\Models\Player;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class GalleryImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'galleryImages';

    protected static ?string $title = 'Foto';

    protected static ?string $modelLabel = 'Foto';

    protected static ?string $pluralModelLabel = 'Foto';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titolo')
                    ->maxLength(255),
                Forms\Components\Select::make('players')
                    ->label('Atlete presenti')
                    ->multiple()
                    ->relationship('players', 'last_name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Attiva')
                    ->default(true),
                Forms\Components\Toggle::make('needs_review')
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
        $table->modifyQueryUsing(fn (Builder $query) => $query->with(['media', 'players']));

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
                Tables\Columns\TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('players.last_name')
                    ->label('Atlete')
                    ->badge()
                    ->separator(', '),
                Tables\Columns\IconColumn::make('needs_review')
                    ->label('Da Rev.')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attiva')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('needs_review')
                    ->label('Da Revisionare'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('analyzeAll')
                    ->label('Analizza tutte con AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Analizza tutte le foto')
                    ->modalDescription('Verranno analizzate tutte le foto di questo evento con l\'Intelligenza Artificiale. L\'operazione avviene in background.')
                    ->action(function () {
                        $images = $this->getOwnerRecord()->galleryImages;
                        foreach ($images as $image) {
                            AnalyzeGalleryImageJob::dispatch($image);
                        }
                        Notification::make()
                            ->title('Analisi AI avviata')
                            ->body($images->count() . ' foto in fase di analisi in background.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('identifica')
                    ->label('Identifica')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->visible(fn (GalleryImage $record) => $record->needs_review)
                    ->mountUsing(function (Forms\Form $form, GalleryImage $record) {
                        $form->fill([
                            'players' => $record->players->pluck('id')->toArray(),
                        ]);
                    })
                    ->form([
                        Forms\Components\Select::make('players')
                            ->label('Atlete presenti')
                            ->multiple()
                            ->options(Player::pluck('last_name', 'id'))
                            ->searchable(),
                    ])
                    ->action(function (GalleryImage $record, array $data) {
                        $record->players()->sync($data['players'] ?? []);
                        $record->needs_review = false;
                        $record->save();
                        Notification::make()->title('Identificata')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (GalleryImage $record) {
                        $record->clearMediaCollection('gallery');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Collection $records) {
                            foreach ($records as $record) {
                                $record->clearMediaCollection('gallery');
                            }
                        }),
                    Tables\Actions\BulkAction::make('analyzeBulk')
                        ->label('Analizza con AI')
                        ->icon('heroicon-o-sparkles')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                AnalyzeGalleryImageJob::dispatch($record);
                            }
                            Notification::make()
                                ->title('Analisi avviata')
                                ->body($records->count() . ' foto in fase di analisi.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
    }
}
