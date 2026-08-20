<?php

namespace App\Services;

use App\Enums\PlayerHonourCategory;
use App\Models\Player;
use App\Models\PlayerHonour;
use Illuminate\Support\Collection;

/**
 * Prepara il palmarès di un'atleta per il banner pubblico.
 *
 * L'aggregazione si fa qui e non nel componente Vue: la pagina rosa è in
 * cache lato server, quindi il lavoro si paga una volta ogni dieci minuti
 * invece che a ogni apertura del banner su ogni dispositivo.
 *
 * In archivio c'è una riga per trofeo; a schermo si legge "2× Coppa CEV
 * (2017-18, 2021-22)". Il raggruppamento è per competizione — e per i trofei
 * in nazionale anche per medaglia, perché un oro e un argento nello stesso
 * torneo sono due voci diverse, non due edizioni della stessa.
 */
class PalmaresPresenter
{
    /**
     * @return array{groups: list<array<string, mixed>>, totals: array<string, int>, total: int, source: array<string, mixed>|null}|null
     *                                                                                                                                   Null quando l'atleta non ha nessun titolo pubblicabile.
     */
    public function forPlayer(Player $player): ?array
    {
        if (! $player->relationLoaded('honours')) {
            $player->load(['honours' => fn ($query) => $query->visible()]);
        }

        $honours = $player->getRelation('honours')
            ->filter(fn (PlayerHonour $honour): bool => $honour->is_visible);

        if ($honours->isEmpty()) {
            return null;
        }

        $groups = [];
        $totals = [];

        foreach (PlayerHonourCategory::cases() as $category) {
            $rows = $honours->filter(fn (PlayerHonour $honour): bool => $honour->category === $category);

            if ($rows->isEmpty()) {
                continue;
            }

            $items = $category === PlayerHonourCategory::Individual
                ? $this->presentAwards($rows)
                : $this->aggregate($rows);

            $groups[] = [
                'category' => $category->value,
                'items' => $items,
            ];

            $totals[$category->value] = $rows->count();
        }

        return [
            'groups' => $groups,
            'totals' => $totals,
            'total' => $honours->count(),
            'source' => $this->source($player),
        ];
    }

    /**
     * Trofei di club e medaglie: righe uguali fuse, edizioni in elenco.
     *
     * @param  Collection<int, PlayerHonour>  $rows
     * @return list<array<string, mixed>>
     */
    private function aggregate(Collection $rows): array
    {
        $items = [];

        foreach ($rows as $honour) {
            $competition = (string) $honour->competition;
            $medal = $honour->medal?->value;
            $key = $medal.'|'.$competition;

            if (! isset($items[$key])) {
                $items[$key] = [
                    'competition' => $competition,
                    'medal' => $medal,
                    'count' => 0,
                    'editions' => [],
                    'year' => null,
                    'note' => null,
                ];
            }

            $items[$key]['count']++;

            if ($honour->edition !== null && $honour->edition !== '') {
                $items[$key]['editions'][] = $honour->edition;
            }

            // Il gruppo si ordina sull'edizione più recente che contiene.
            $items[$key]['year'] = max($items[$key]['year'] ?? 0, (int) $honour->year) ?: null;
        }

        $items = array_values($items);

        // Prima l'oro, poi l'argento, poi il bronzo: una bacheca si legge dal
        // metallo, non dal numero di volte. I titoli di club non hanno medaglia
        // e restano ordinati per quantità e recency.
        $rank = static fn (?string $medal): int => match ($medal) {
            'gold', null => 0,
            'silver' => 1,
            default => 2,
        };

        usort($items, fn (array $a, array $b): int => [$rank($a['medal']), -$a['count'], -($a['year'] ?? 0)]
            <=> [$rank($b['medal']), -$b['count'], -($b['year'] ?? 0)]);

        return $items;
    }

    /**
     * I premi individuali non si fondono: "Miglior palleggiatrice" agli Europei
     * 2011 e nel 2019 sono due riconoscimenti, non uno vinto due volte.
     *
     * @param  Collection<int, PlayerHonour>  $rows
     * @return list<array<string, mixed>>
     */
    private function presentAwards(Collection $rows): array
    {
        return $rows
            ->sortByDesc(fn (PlayerHonour $honour): int => (int) $honour->year)
            ->map(fn (PlayerHonour $honour): array => [
                'competition' => (string) $honour->competition,
                'medal' => null,
                'count' => 1,
                'editions' => array_filter([$honour->edition]),
                'year' => $honour->year,
                'note' => $honour->note !== null && $honour->note !== '' ? (string) $honour->note : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Attribuzione della fonte: i contenuti di Wikipedia sono CC BY-SA e il
     * link alla voce è anche il modo più rapido per verificare un dato.
     *
     * @return array<string, mixed>|null
     */
    private function source(Player $player): ?array
    {
        if ($player->wikipedia_title === null) {
            return null;
        }

        $lang = $player->wikipedia_lang ?? 'it';

        return [
            'name' => 'Wikipedia',
            'url' => sprintf('https://%s.wikipedia.org/wiki/%s', $lang, rawurlencode(str_replace(' ', '_', $player->wikipedia_title))),
            'syncedAt' => $player->palmares_synced_at?->toIso8601String(),
        ];
    }
}
