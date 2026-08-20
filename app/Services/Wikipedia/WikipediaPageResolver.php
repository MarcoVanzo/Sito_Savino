<?php

namespace App\Services\Wikipedia;

use App\Models\Player;
use Illuminate\Support\Str;

/**
 * Trova la voce di Wikipedia che corrisponde a un'atleta.
 *
 * L'ordine conta: prima l'aggancio già salvato in anagrafica (che è una scelta
 * della redazione e non si rimette in discussione), poi il titolo esatto
 * "Nome Cognome", infine la ricerca a testo pieno — che serve davvero: sulla
 * rosa 2026/27 tredici atlete su quattordici hanno il titolo esatto, ma Julia
 * Bergmann sta su "Júlia Bergmann".
 *
 * Ogni candidata viene verificata: la voce deve essere di una pallavolista e,
 * quando l'anagrafica ha la data di nascita, l'anno deve comparire nella voce.
 * Senza questi due controlli un omonimo qualsiasi finirebbe in pagina.
 */
class WikipediaPageResolver
{
    private const MAX_CANDIDATES_INSPECTED = 4;

    public function __construct(private readonly WikipediaClient $client) {}

    /**
     * Voce agganciata all'atleta, o null se non se ne trova una attendibile.
     *
     * @return array{title: string, revid: int, wikitext: string, url: string, confidence: string, alternatives: list<string>}|null
     */
    public function resolve(Player $player, ?string $forcedTitle = null): ?array
    {
        $forcedTitle = $forcedTitle !== null ? trim($forcedTitle) : null;

        // Titolo imposto (o già salvato): si prende com'è, senza verifiche.
        // Se la redazione ha scelto quella voce, ha ragione lei.
        foreach (array_filter([$forcedTitle, $player->wikipedia_title]) as $title) {
            $page = $this->client->page((string) $title);

            if ($page !== null) {
                return $this->present($page, 'confermata', []);
            }
        }

        $fullName = trim("{$player->first_name} {$player->last_name}");
        $alternatives = [];

        $exact = $this->client->page($fullName);

        if ($exact !== null && $this->isVolleyballPlayer($exact['wikitext'])) {
            return $this->present(
                $exact,
                $this->birthYearMatches($player, $exact['wikitext']) === false ? 'da verificare' : 'sicura',
                [],
            );
        }

        $best = null;

        foreach (array_slice($this->client->search("{$fullName} pallavolista"), 0, self::MAX_CANDIDATES_INSPECTED) as $result) {
            $candidate = $this->client->page($result['title']);

            if ($candidate === null || ! $this->isVolleyballPlayer($candidate['wikitext'])) {
                continue;
            }

            $alternatives[] = $candidate['title'];

            if ($best !== null) {
                continue;
            }

            $birthMatches = $this->birthYearMatches($player, $candidate['wikitext']);

            // Con la data di nascita in anagrafica si accetta solo chi la
            // conferma; senza, si prende il primo risultato ma lo si segnala.
            if ($birthMatches === true) {
                $best = [$candidate, 'sicura'];
            } elseif ($birthMatches === null && $this->nameLooksClose($fullName, $candidate['title'])) {
                $best = [$candidate, 'da verificare'];
            }
        }

        if ($best === null) {
            return null;
        }

        [$page, $confidence] = $best;

        return $this->present($page, $confidence, array_values(array_diff($alternatives, [$page['title']])));
    }

    /**
     * Titoli proposti dalla ricerca, per la tendina di correzione manuale.
     *
     * @return list<array{title: string, snippet: string}>
     */
    public function suggestions(Player $player): array
    {
        return $this->client->search(trim("{$player->first_name} {$player->last_name}").' pallavolista');
    }

    /**
     * @param  array{title: string, pageid: int, revid: int, wikitext: string}  $page
     * @param  list<string>  $alternatives
     * @return array{title: string, revid: int, wikitext: string, url: string, confidence: string, alternatives: list<string>}
     */
    private function present(array $page, string $confidence, array $alternatives): array
    {
        return [
            'title' => $page['title'],
            'revid' => $page['revid'],
            'wikitext' => $page['wikitext'],
            'url' => $this->client->pageUrl($page['title']),
            'confidence' => $confidence,
            'alternatives' => $alternatives,
        ];
    }

    private function isVolleyballPlayer(string $wikitext): bool
    {
        $normalized = Str::of($wikitext)->ascii()->lower()->toString();

        return str_contains($normalized, 'pallavolist')
            || str_contains($normalized, 'disciplina = pallavolo')
            || str_contains($normalized, 'portale|pallavolo')
            || str_contains($normalized, 'volleyball player');
    }

    /**
     * True/false quando l'anagrafica ha la data di nascita, null quando manca
     * e quindi il controllo non si può fare.
     */
    private function birthYearMatches(Player $player, string $wikitext): ?bool
    {
        if ($player->date_of_birth === null) {
            return null;
        }

        $incipit = Str::substr($wikitext, 0, 4000);

        return str_contains($incipit, (string) $player->date_of_birth->year);
    }

    /**
     * Gli accenti sono la differenza tipica fra anagrafica e Wikipedia
     * (Julia/Júlia, Aleksic/Aleksić): il confronto si fa senza.
     */
    private function nameLooksClose(string $fullName, string $title): bool
    {
        $normalize = fn (string $value): string => Str::of($value)->ascii()->lower()->squish()->toString();

        return $normalize($fullName) === $normalize($title);
    }
}
