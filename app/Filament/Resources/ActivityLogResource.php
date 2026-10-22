<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\RevocaDelRiconoscimentoDeiVolti;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $modelLabel = 'Registro Attività';

    protected static ?string $pluralModelLabel = 'Registro Attività';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Log Attività';

    protected static string|\UnitEnum|null $navigationGroup = 'Amministrazione';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'log';

    protected static bool $isGloballySearchable = false;

    /**
     * Solo gli admin possono vedere i log.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->role === UserRole::SuperAdmin;
    }

    /**
     * Nessun form — i log sono di sola lettura.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action')
                    ->label('Azione')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->action_label)
                    ->icon(fn ($record) => $record->action_icon)
                    ->color(fn ($record) => $record->action_color)
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Utente')
                    ->default('Sistema')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('model_type')
                    ->label('Risorsa')
                    ->formatStateUsing(fn ($record) => $record->model_type_label)
                    ->sortable(),

                TextColumn::make('model_label')
                    ->label('Dettaglio')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(fn ($record) => $record->model_label),

                TextColumn::make('model_id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Data / Ora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Utente')
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->searchable(),

                SelectFilter::make('action')
                    ->label('Azione')
                    ->options([
                        'created' => 'Creazione',
                        'updated' => 'Modifica',
                        'deleted' => 'Eliminazione',
                        'restored' => 'Ripristino',
                        'force_deleted' => 'Eliminazione definitiva',
                        RevocaDelRiconoscimentoDeiVolti::AZIONE => 'Revoca riconoscimento volti',
                    ]),

                SelectFilter::make('model_type')
                    ->label('Tipo Risorsa')
                    ->options(fn () => Cache::remember('activity_log_model_types', 3600, fn () => ActivityLog::query()
                        ->distinct()
                        ->pluck('model_type')
                        ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
                        ->toArray()
                    )),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Da'),
                        DatePicker::make('until')
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
                            $indicators[] = Indicator::make('Da '.Carbon::parse($data['from'])->format('d/m/Y'))
                                ->removeField('from');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('A '.Carbon::parse($data['until'])->format('d/m/Y'))
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);  // Nessuna bulk action — log immutabili
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli Azione')
                    ->schema([
                        TextEntry::make('action')
                            ->label('Azione')
                            ->badge()
                            ->formatStateUsing(fn ($record) => $record->action_label)
                            ->color(fn ($record) => $record->action_color),

                        TextEntry::make('user.name')
                            ->label('Utente')
                            ->default('Sistema (CLI)'),

                        TextEntry::make('created_at')
                            ->label('Data e Ora')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('model_type')
                            ->label('Tipo Risorsa')
                            ->formatStateUsing(fn ($record) => $record->model_type_label),

                        TextEntry::make('model_label')
                            ->label('Record'),

                        TextEntry::make('model_id')
                            ->label('ID Record'),

                        TextEntry::make('ip_address')
                            ->label('Indirizzo IP')
                            ->default('—'),

                        TextEntry::make('user_agent')
                            ->label('Browser / Client')
                            ->default('—')
                            ->columnSpanFull(),
                    ])->columns(3),

                // Sezione diff modifiche — visibile solo se ci sono changes
                Section::make('Modifiche')
                    ->schema([
                        ViewEntry::make('changes')
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
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
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
