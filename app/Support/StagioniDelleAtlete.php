<?php

namespace App\Support;

use App\Models\Player;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Sa dire se un'atleta era nella squadra quando è stata scattata una foto.
 *
 * Il riconoscimento dei volti scambia le atlete fra loro (le nuove arrivate
 * finivano taggate su foto di stagioni in cui giocavano altrove), e l'archivio
 * conserva solo la rosa della stagione in corso. Le stagioni passate stanno in
 * `database/data/stagioni_delle_atlete.php`; quelle con una rosa nel pannello
 * si aggiungono da sé. Un'atleta che il file non conosce non viene giudicata:
 * meglio un tag sbagliato in più che uno giusto tolto.
 */
class StagioniDelleAtlete
{
    /** @var array<string, list<int>>|null */
    private static ?array $dalFile = null;

    /**
     * true = era in squadra, false = non c'era, null = non si sa (atleta che il
     * file non elenca, o foto senza data).
     */
    public static function eraInSquadra(Player $atleta, ?CarbonInterface $data): ?bool
    {
        if ($data === null) {
            return null;
        }

        $stagioni = self::stagioniDi($atleta);

        if ($stagioni === null) {
            return null;
        }

        return in_array(self::stagioneDi($data), $stagioni, true);
    }

    /**
     * Anno d'apertura della stagione: la stagione 2025/26 va dal 1º luglio
     * 2025 al 30 giugno 2026.
     */
    public static function stagioneDi(CarbonInterface $data): int
    {
        return $data->month >= 7 ? $data->year : $data->year - 1;
    }

    /**
     * @return list<int>|null
     */
    public static function stagioniDi(Player $atleta): ?array
    {
        $dalFile = self::dalFile()[self::chiave($atleta->first_name.' '.$atleta->last_name)] ?? null;

        if ($dalFile === null) {
            return null;
        }

        $dalleRose = $atleta->rosters()->with('season')->get()
            ->map(fn ($riga) => $riga->season->lvf_season_year
                ?? (int) Str::before((string) $riga->season->name, '/'))
            ->filter()
            ->all();

        return array_values(array_unique(array_map('intval', [...$dalFile, ...$dalleRose])));
    }

    /** @return array<string, list<int>> */
    private static function dalFile(): array
    {
        if (self::$dalFile === null) {
            self::$dalFile = [];

            foreach (require database_path('data/stagioni_delle_atlete.php') as $nome => $stagioni) {
                self::$dalFile[self::chiave($nome)] = $stagioni;
            }
        }

        return self::$dalFile;
    }

    /** Per i test, che sostituiscono il file. */
    public static function usa(?array $stagioni): void
    {
        self::$dalFile = $stagioni === null ? null : collect($stagioni)
            ->mapWithKeys(fn ($s, $nome) => [self::chiave($nome) => $s])->all();
    }

    private static function chiave(string $nome): string
    {
        return Str::of($nome)->ascii()->lower()->squish()->toString();
    }
}
