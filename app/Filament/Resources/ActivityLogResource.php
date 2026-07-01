<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $modelLabel = 'Registro Attività';

    protected static ?string $pluralModelLabel = 'Registro Attività';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Log Attività';

    protected static ?string $navigationGroup = 'Amministrazione';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'log';

    /**
     * Solo gli admin possono vedere i log.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->role === UserRole::Admin;
    }

    /**
     * Nessun form — i log sono di sola lettura.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->label('Azione')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->action_label)
                    ->icon(fn ($record) => $record->action_icon)
                    ->color(fn ($record) => $record->action_color)
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utente')
                    ->default('Sistema')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('model_type')
                    ->label('Risorsa')
                    ->formatStateUsing(fn ($record) => $record->model_type_label)
                    ->sortable(),

                Tables\Columns\TextColumn::make('model_label')
                    ->label('Dettaglio')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(fn ($record) => $record->model_label),

                Tables\Columns\TextColumn::make('model_id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data / Ora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Utente')
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('action')
                    ->label('Azione')
                    ->options([
                        'created' => 'Creazione',
                        'updated' => 'Modifica',
                        'deleted' => 'Eliminazione',
                        'restored' => 'Ripristino',
                        'force_deleted' => 'Eliminazione definitiva',
                    ]),

                Tables\Filters\SelectFilter::make('model_type')
                    ->label('Tipo Risorsa')
                    ->options(fn () => \Illuminate\Support\Facades\Cache::remember('activity_log_model_types', 3600, fn() =>
                        ActivityLog::query()
                            ->distinct()
                            ->pluck('model_type')
                            ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
                            ->toArray()
                    )),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Da'),
                        Forms\Components\DatePicker::make('until')
                            ->label('A'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Da '.Carbon::parse($data['from'])->format('d/m/Y'))
                                ->removeField('from');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('A '.Carbon::parse($data['until'])->format('d/m/Y'))
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])  // Nessuna bulk action — log immutabili
            ->poll('30s'); // Auto-refresh ogni 30 secondi
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Dettagli Azione')
                    ->schema([
                        Infolists\Components\TextEntry::make('action')
                            ->label('Azione')
                            ->badge()
                            ->formatStateUsing(fn ($record) => $record->action_label)
                            ->color(fn ($record) => $record->action_color),

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Utente')
                            ->default('Sistema (CLI)'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Data e Ora')
                            ->dateTime('d/m/Y H:i:s'),

                        Infolists\Components\TextEntry::make('model_type')
                            ->label('Tipo Risorsa')
                            ->formatStateUsing(fn ($record) => $record->model_type_label),

                        Infolists\Components\TextEntry::make('model_label')
                            ->label('Record'),

                        Infolists\Components\TextEntry::make('model_id')
                            ->label('ID Record'),

                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('Indirizzo IP')
                            ->default('—'),

                        Infolists\Components\TextEntry::make('user_agent')
                            ->label('Browser / Client')
                            ->default('—')
                            ->columnSpanFull(),
                    ])->columns(3),

                // Sezione diff modifiche — visibile solo se ci sono changes
                Infolists\Components\Section::make('Modifiche')
                    ->schema([
                        Infolists\Components\ViewEntry::make('changes')
                            ->label('')
                            ->view('filament.infolists.entries.activity-changes'),
                    ])
                    ->visible(fn ($record) => ! empty($record->changes))
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'media']);
    }

    /**
     * Disabilita creazione/modifica/eliminazione.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
