<?php

namespace App\Filament\Resources;

use App\Enums\CodiceDelRedirect;
use App\Filament\Resources\ReindirizzamentoResource\Pages\CreateReindirizzamento;
use App\Filament\Resources\ReindirizzamentoResource\Pages\EditReindirizzamento;
use App\Filament\Resources\ReindirizzamentoResource\Pages\ListReindirizzamenti;
use App\Models\Reindirizzamento;
use App\Support\RedirectGestiti;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * I redirect del vecchio sito decisi a mano: vincono sulle regole generali di
 * `legacy.php` e sul permalink automatico delle notizie. Si importano anche
 * da CSV con `php artisan redirect:importa`.
 */
class ReindirizzamentoResource extends Resource
{
    protected static ?string $model = Reindirizzamento::class;

    protected static ?string $recordTitleAttribute = 'from_path';

    protected static ?string $modelLabel = 'Redirect';

    protected static ?string $pluralModelLabel = 'Redirect';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Amministrazione';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema(self::campi())
                ->columns(1),
        ]);
    }

    /**
     * I campi del redirect, condivisi con «Crea redirect» dei 404 da mappare.
     *
     * @return list<Component>
     */
    public static function campi(): array
    {
        return [
            TextInput::make('from_path')
                ->label('Vecchio indirizzo')
                ->helperText('Solo il percorso, es. /tag/challenge-cup/ o un indirizzo intero del vecchio sito: si salva senza query, senza slash finale e in minuscolo.')
                ->required()
                ->maxLength(1024)
                ->rules([
                    fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        // Dai «404 da mappare» il record è l'indirizzo, non un redirect.
                        $proprio = $record instanceof Reindirizzamento ? $record : null;

                        $errore = RedirectGestiti::erroreNellaPartenza((string) $value);

                        if ($errore !== null) {
                            $fail($errore);

                            return;
                        }

                        $giaMappato = Reindirizzamento::query()
                            ->where('from_path', RedirectGestiti::normalizza((string) $value))
                            ->when($proprio?->exists, fn ($query) => $query->whereKeyNot($proprio?->getKey()))
                            ->exists();

                        if ($giaMappato) {
                            $fail('Questo indirizzo ha già un redirect.');
                        }
                    },
                ]),
            Select::make('status_code')
                ->label('Risposta')
                ->options(CodiceDelRedirect::class)
                ->default(CodiceDelRedirect::Permanente->value)
                ->required()
                ->live(),
            TextInput::make('to_path')
                ->label('Destinazione')
                ->helperText('Un percorso del sito (/news/…) o un indirizzo https://. Vuota per il 410.')
                ->maxLength(2048)
                ->visible(fn (Get $get): bool => self::codice($get('status_code'))->vuoleLaDestinazione())
                ->required(fn (Get $get): bool => self::codice($get('status_code'))->vuoleLaDestinazione())
                ->rules([
                    fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $errore = RedirectGestiti::erroreNellaDestinazione(
                            (string) $get('from_path'),
                            (string) $value,
                            self::codice($get('status_code')),
                        );

                        if ($errore !== null) {
                            $fail($errore);
                        }
                    },
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')
                    ->label('Vecchio indirizzo')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('status_code')
                    ->label('Risposta')
                    ->badge(),
                TextColumn::make('to_path')
                    ->label('Destinazione')
                    ->searchable()
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('hits')
                    ->label('Visite')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_hit_at')
                    ->label('Ultima visita')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('mai'),
            ])
            ->filters([
                SelectFilter::make('status_code')
                    ->label('Risposta')
                    ->options(CodiceDelRedirect::class),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ListReindirizzamenti::route('/'),
            'create' => CreateReindirizzamento::route('/create'),
            'edit' => EditReindirizzamento::route('/{record}/edit'),
        ];
    }

    private static function codice(mixed $valore): CodiceDelRedirect
    {
        if ($valore instanceof CodiceDelRedirect) {
            return $valore;
        }

        return CodiceDelRedirect::tryFrom((string) $valore) ?? CodiceDelRedirect::Permanente;
    }
}
