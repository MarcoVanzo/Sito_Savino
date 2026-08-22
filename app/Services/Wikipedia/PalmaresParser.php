<?php

namespace App\Services\Wikipedia;

use App\Enums\HonourMedal;
use App\Enums\PlayerHonourCategory;
use Illuminate\Support\Str;

/**
 * Estrae il palmarès dal wikitesto di una voce di pallavolista su it.wikipedia.
 *
 * Due sorgenti nella stessa pagina, entrambe necessarie:
 *
 * 1. L'infobox `{{Sportivo}}`, che porta le medaglie con la nazionale maggiore
 *    come coppie `{{MedaglieCompetizione|…}}` + `{{MedaglieOro|…}}`.
 * 2. La sezione `== Palmarès ==`, divisa in `=== Club ===`,
 *    `=== Nazionale … ===` e `=== Premi individuali ===`.
 *
 * Nella sezione Club il titolo arriva da `{{Pallavolopalm|Competizione|n}}` e
 * gli anni dalla riga `:` immediatamente successiva. Attenzione: il secondo
 * parametro del template NON è il numero di titoli — il template lo ignora e
 * su Wikipedia è compilato a caso. Il conteggio si fa sugli anni, ed è per
 * questo che qui si emette una riga per anno.
 *
 * Le medaglie compaiono in due varianti storiche, `{{simbolo|Gold medal …}}` e
 * `{{Med|O|Mondo}}`: vanno riconosciute entrambe, o le atlete giovani (che
 * hanno solo medaglie di categoria) risultano senza palmarès.
 */
class PalmaresParser
{
    /**
     * @return list<array{category: string, competition: string, edition: ?string, year: ?int, medal: ?string, note: ?string}>
     */
    public function parse(string $wikitext): array
    {
        $wikitext = str_replace("\r\n", "\n", $wikitext);

        $honours = [
            ...$this->parseInfoboxMedals($wikitext),
            ...$this->parsePalmaresSection($wikitext),
        ];

        return $this->deduplicate($honours);
    }

    /**
     * Medaglie con la nazionale maggiore, dall'infobox.
     *
     * @return list<array<string, mixed>>
     */
    private function parseInfoboxMedals(string $wikitext): array
    {
        // Solo la parte prima della prima intestazione: dopo comincia il corpo
        // della voce, dove gli stessi template non compaiono.
        $head = preg_split('/^==[^=]/m', $wikitext, 2)[0] ?? $wikitext;

        $honours = [];
        $competition = null;

        foreach (preg_split('/\n/', $head) ?: [] as $line) {
            if (preg_match('/\{\{\s*MedaglieCompetizione\s*\|(.+?)\}\}\s*$/iu', trim($line), $m) === 1) {
                $competition = $this->normalizeCompetition($this->plainText($m[1]));

                continue;
            }

            if (preg_match('/\{\{\s*Medaglie(Oro|Argento|Bronzo)\s*\|(.+?)\}\}\s*$/iu', trim($line), $m) !== 1) {
                continue;
            }

            if ($competition === null || $competition === '') {
                continue;
            }

            $edition = $this->plainText($m[2]);

            $honours[] = [
                'category' => PlayerHonourCategory::National->value,
                'competition' => $competition,
                'edition' => $edition !== '' ? $edition : null,
                'year' => $this->extractYear($edition),
                'medal' => $this->medalFromItalian($m[1]),
                'note' => null,
            ];
        }

        return $honours;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsePalmaresSection(string $wikitext): array
    {
        $section = $this->extractSection($wikitext, 'Palmarès');

        if ($section === null) {
            return [];
        }

        $honours = [];
        $category = PlayerHonourCategory::Club;
        /** @var array<string, mixed>|null $pending Titolo di club in attesa della riga degli anni. */
        $pending = null;

        foreach (preg_split('/\n/', $section) ?: [] as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^=+\s*(.+?)\s*=+$/u', $line, $m) === 1) {
                $category = $this->categoryFromHeading($m[1]);
                $pending = null;

                continue;
            }

            // Riga degli anni di un titolo di club: ": [[…|2017-18]], [[…|2021-22]]".
            if (str_starts_with($line, ':')) {
                if ($pending !== null) {
                    $honours = [...$honours, ...$this->edizioniDelTitolo($pending, ltrim($line, ': '))];
                    $pending = null;
                }

                continue;
            }

            if (! str_starts_with($line, '*')) {
                continue;
            }

            // Il titolo di club precedente non aveva la riga degli anni: si
            // pubblica lo stesso, senza edizione, invece di perderlo.
            if ($pending !== null) {
                $honours[] = $pending;
                $pending = null;
            }

            $item = trim(ltrim($line, '* '));

            if ($category === PlayerHonourCategory::Individual) {
                $honour = $this->parseIndividualAward($item);

                if ($honour !== null) {
                    $honours[] = $honour;
                }

                continue;
            }

            $titoloDiClub = $this->titoloDiClub($item);

            if ($titoloDiClub !== null) {
                // Gli anni arrivano dalla riga successiva: si tiene in sospeso.
                $pending = $titoloDiClub;

                continue;
            }

            $titoloConNazionale = $this->titoloConNazionale($item);

            if ($titoloConNazionale !== null) {
                $honours[] = $titoloConNazionale;
            }
        }

        if ($pending !== null) {
            $honours[] = $pending;
        }

        return $honours;
    }

    /**
     * Un titolo di club vinto in piu' edizioni produce una riga per edizione.
     *
     * @param  array<string, mixed>  $titolo
     * @return list<array<string, mixed>>
     */
    private function edizioniDelTitolo(array $titolo, string $riga): array
    {
        $honours = [];

        foreach ($this->splitEditions($riga) as $edition) {
            $honours[] = [...$titolo, 'edition' => $edition, 'year' => $this->extractYear($edition)];
        }

        return $honours;
    }

    /**
     * Titolo di club, riconosciuto dal template `Pallavolopalm`. Resta senza
     * edizione: gli anni stanno nella riga successiva.
     *
     * @return array<string, mixed>|null
     */
    private function titoloDiClub(string $item): ?array
    {
        if (preg_match('/\{\{\s*Pallavolopalm\s*\|\s*([^|}]+)/iu', $item, $m) !== 1) {
            return null;
        }

        return [
            'category' => PlayerHonourCategory::Club->value,
            'competition' => $this->normalizeCompetition($this->plainText($m[1])),
            'edition' => null,
            'year' => null,
            'medal' => null,
            'note' => null,
        ];
    }

    /**
     * Titolo con la nazionale: la medaglia e' nel markup, competizione ed
     * edizione nel testo che resta una volta tolti i template.
     *
     * @return array<string, mixed>|null
     */
    private function titoloConNazionale(string $item): ?array
    {
        $medal = $this->medalFromMarkup($item);

        if ($medal === null) {
            return null;
        }

        $label = $this->plainText($this->stripTemplates($item));

        if ($label === '') {
            return null;
        }

        [$competition, $edition] = $this->splitCompetitionAndEdition($label);

        return [
            'category' => PlayerHonourCategory::National->value,
            'competition' => $competition,
            'edition' => $edition,
            'year' => $this->extractYear($edition ?? $label),
            'medal' => $medal->value,
            'note' => null,
        ];
    }

    /**
     * "2011 - [[…|Campionato europeo]]: Miglior palleggiatrice"
     *
     * @return array<string, mixed>|null
     */
    private function parseIndividualAward(string $item): ?array
    {
        $text = $this->plainText($this->stripTemplates($item));

        if ($text === '') {
            return null;
        }

        $year = null;

        if (preg_match('/^((?:19|20)\d{2})\s*[-–—]\s*(.+)$/u', $text, $m) === 1) {
            $year = (int) $m[1];
            $text = trim($m[2]);
        }

        $competition = null;
        $note = $text;

        if (preg_match('/^(.+?)\s*:\s*(.+)$/u', $text, $m) === 1) {
            $competition = trim($m[1]);
            $note = trim($m[2]);
        }

        if ($note === '') {
            return null;
        }

        return [
            'category' => PlayerHonourCategory::Individual->value,
            'competition' => $this->normalizeCompetition($competition ?? $note),
            'edition' => $year !== null ? (string) $year : null,
            'year' => $year,
            'medal' => null,
            'note' => $competition !== null ? $note : null,
        ];
    }

    /**
     * Sezione di primo livello, dalla sua intestazione alla successiva.
     */
    private function extractSection(string $wikitext, string $heading): ?string
    {
        $pattern = '/^==\s*'.preg_quote($heading, '/').'\s*==\s*$(.*?)(?=^==[^=]|\z)/msu';

        return preg_match($pattern, $wikitext, $m) === 1 ? $m[1] : null;
    }

    private function categoryFromHeading(string $heading): PlayerHonourCategory
    {
        $normalized = Str::of($heading)->ascii()->lower()->toString();

        return match (true) {
            str_contains($normalized, 'individual') => PlayerHonourCategory::Individual,
            str_contains($normalized, 'nazionale') => PlayerHonourCategory::National,
            default => PlayerHonourCategory::Club,
        };
    }

    /**
     * ": [[Sultanlar Ligi 2020-2021|2020-21]], [[…|2021-22]]" → ["2020-21", "2021-22"]
     *
     * @return list<string>
     */
    private function splitEditions(string $line): array
    {
        $editions = [];

        foreach (explode(',', $this->plainText($line)) as $chunk) {
            $chunk = trim($chunk);

            if ($chunk !== '') {
                $editions[] = $chunk;
            }
        }

        return $editions;
    }

    /**
     * "European League 2010" → ["European League", "2010"]
     *
     * @return array{0: string, 1: ?string}
     */
    private function splitCompetitionAndEdition(string $label): array
    {
        if (preg_match('/^(.*?)\s+((?:19|20)\d{2}(?:[-\/]\d{2,4})?)$/u', $label, $m) === 1 && trim($m[1]) !== '') {
            return [$this->normalizeCompetition($m[1]), $m[2]];
        }

        return [$this->normalizeCompetition($label), null];
    }

    private function medalFromItalian(string $word): string
    {
        return match (Str::lower($word)) {
            'oro' => HonourMedal::Gold->value,
            'argento' => HonourMedal::Silver->value,
            default => HonourMedal::Bronze->value,
        };
    }

    /**
     * Riconosce le due notazioni in uso: `{{simbolo|Gold medal europe.svg}}` e
     * la più recente `{{Med|O|Mondo}}`.
     */
    private function medalFromMarkup(string $item): ?HonourMedal
    {
        if (preg_match('/\{\{\s*simbolo\s*\|\s*([^|}]+)/iu', $item, $m) === 1) {
            $file = Str::lower($m[1]);

            return match (true) {
                str_contains($file, 'gold') => HonourMedal::Gold,
                str_contains($file, 'silver') => HonourMedal::Silver,
                str_contains($file, 'bronze') => HonourMedal::Bronze,
                default => null,
            };
        }

        if (preg_match('/\{\{\s*Med\s*\|\s*([OABoab])\s*[|}]/u', $item, $m) === 1) {
            return match (Str::upper($m[1])) {
                'O' => HonourMedal::Gold,
                'A' => HonourMedal::Silver,
                'B' => HonourMedal::Bronze,
                default => null,
            };
        }

        return null;
    }

    /**
     * Il suffisso di genere sta nel nome del template, non in quello del
     * torneo: "Coppa CEV femminile" si pubblica come "Coppa CEV".
     */
    private function normalizeCompetition(string $competition): string
    {
        return Str::of($competition)
            ->replaceMatches('/\s+(femminile|maschile)$/iu', '')
            ->squish()
            ->toString();
    }

    private function extractYear(?string $text): ?int
    {
        if ($text === null || preg_match('/(19|20)\d{2}/u', $text, $m) !== 1) {
            return null;
        }

        return (int) $m[0];
    }

    private function stripTemplates(string $text): string
    {
        return (string) preg_replace('/\{\{[^{}]*\}\}/u', ' ', $text);
    }

    /**
     * Wikitesto → testo leggibile: link, riferimenti, corsivi e template via.
     */
    private function plainText(string $text): string
    {
        $text = (string) preg_replace('/<ref[^>]*\/>/u', '', $text);
        $text = (string) preg_replace('/<ref[^>]*>.*?<\/ref>/su', '', $text);
        $text = (string) preg_replace('/\[\[[^]|]*\|([^]]*)\]\]/u', '$1', $text);
        $text = (string) preg_replace('/\[\[([^]]*)\]\]/u', '$1', $text);
        $text = $this->stripTemplates($text);
        $text = (string) preg_replace("/'{2,}/u", '', $text);
        $text = strip_tags($text);

        return Str::of($text)->squish()->trim(" \t\n\r\0\x0B.,;")->toString();
    }

    /**
     * @param  list<array<string, mixed>>  $honours
     * @return list<array<string, mixed>>
     */
    private function deduplicate(array $honours): array
    {
        $seen = [];
        $unique = [];

        foreach ($honours as $honour) {
            $key = Str::of(implode('|', [
                (string) $honour['category'],
                (string) $honour['competition'],
                (string) ($honour['edition'] ?? ''),
                (string) ($honour['note'] ?? ''),
            ]))->ascii()->lower()->squish()->toString();

            if (isset($seen[$key]) || $honour['competition'] === '') {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $honour;
        }

        return $unique;
    }
}
