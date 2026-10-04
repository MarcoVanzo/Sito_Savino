<?php

namespace App\Filament\Forms;

use App\Models\Player;
use App\Models\Team;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Builder;

/**
 * Il campo Atleta dei roster (prima squadra e vivaio).
 *
 * Nel vivaio l'elenco proponeva solo le atlete della Serie A e non c'era modo
 * di aggiungerne una (segreteria, 04/10/2026). Ora l'elenco iniziale del vivaio
 * propone le sue atlete e quelle senza squadra; la ricerca trova comunque
 * tutte, perché le giovani della prima squadra giocano spesso anche nel
 * vivaio e, nascoste, verrebbero ricreate doppie. Per lo stesso motivo la
 * creazione dal "+" rifiuta un nome già presente.
 */
class CampoAtleta
{
    private const RISULTATI = 50;

    public static function make(bool $vivaio = false): Select
    {
        return Select::make('player_id')
            ->label('Atleta')
            ->searchable()
            ->options(fn (): array => self::etichette(
                Player::query()
                    ->when($vivaio, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                        ->whereDoesntHave('rosters')
                        ->orWhereHas('rosters.team', fn (Builder $t) => $t->whereIn('category', Team::CATEGORIE_VIVAIO))))
                    ->orderBy('last_name')
                    ->orderBy('first_name'),
                false,
            ))
            ->getSearchResultsUsing(fn (string $search): array => self::etichette(
                Player::query()
                    ->where(fn (Builder $q) => $q
                        ->where('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]))
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->limit(self::RISULTATI),
                $vivaio,
            ))
            ->getOptionLabelUsing(fn ($value): ?string => ($player = Player::find($value)) ? self::nome($player) : null)
            ->createOptionForm([
                TextInput::make('last_name')
                    ->label('Cognome')
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $esistente = Player::query()
                            ->where('last_name', trim((string) $value))
                            ->where('first_name', trim((string) $get('first_name')))
                            ->first();

                        if ($esistente) {
                            $fail('«'.self::nome($esistente).'» esiste già: cercala nel campo Atleta invece di crearla.');
                        }
                    }),
                TextInput::make('first_name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('date_of_birth')
                    ->label('Data di Nascita'),
                TextInput::make('nationality')
                    ->label('Nazionalità')
                    ->maxLength(255),
            ])
            ->createOptionModalHeading('Nuova atleta')
            ->createOptionUsing(fn (array $data): int => Player::create([
                ...$data,
                'last_name' => trim($data['last_name']),
                'first_name' => trim($data['first_name']),
            ])->getKey());
    }

    /**
     * @param  Builder<Player>  $query
     * @return array<int, string>
     */
    private static function etichette(Builder $query, bool $segnaLaPrimaSquadra): array
    {
        if ($segnaLaPrimaSquadra) {
            $query->withExists(['rosters as in_prima_squadra' => fn (Builder $r) => $r
                ->whereHas('team', fn (Builder $t) => $t->whereNotIn('category', Team::CATEGORIE_VIVAIO))]);
        }

        return $query->get()
            ->mapWithKeys(fn (Player $player): array => [
                $player->getKey() => self::nome($player).($segnaLaPrimaSquadra && $player->getAttribute('in_prima_squadra') ? ' · prima squadra' : ''),
            ])
            ->all();
    }

    private static function nome(Player $player): string
    {
        return trim("{$player->last_name} {$player->first_name}");
    }
}
