<?php

namespace App\Services\Lvf;

use App\Services\Lvf\Data\LvfMatch;
use Carbon\CarbonImmutable;
use DOMElement;
use DOMNode;

/**
 * Estrae le gare dalle pagine `/calendario/` e `/risultati/` della Lega.
 *
 * Le due pagine usano la stessa tabella `table.risultati` ma con intestazioni in
 * ordine diverso: il calendario espone competizione e giornata, i risultati no,
 * e solo i risultati portano i set vinti in `th.num`. Per questo i campi vengono
 * riconosciuti dalla loro forma (codice gara, data, ora, giornata) e non dalla
 * posizione nella riga: un cambio d'ordine a monte non rompe il parser.
 */
class LvfMatchParser
{
    /**
     * @return list<LvfMatch>
     */
    public function parse(string $html): array
    {
        $document = LvfDocument::fromHtml($html);

        $matches = [];

        foreach ($document->xpath->query("//table[contains(@class, 'risultati')]") ?: [] as $table) {
            $match = $this->parseTable($document, $table);

            if ($match instanceof LvfMatch) {
                $matches[] = $match;
            }
        }

        return $matches;
    }

    private function parseTable(LvfDocument $document, DOMNode $table): ?LvfMatch
    {
        $header = $this->parseHeader($document, $table);

        if ($header['matchCenterId'] === null || $header['date'] === null) {
            // Senza identificativo del Match Center non esiste una chiave stabile
            // per l'upsert, e senza data la gara non è collocabile: si scarta.
            return null;
        }

        $teams = $this->parseTeams($document, $table);

        if (count($teams) !== 2) {
            return null;
        }

        [$home, $away] = $teams;

        return new LvfMatch(
            lvfMatchId: $header['matchCenterId'],
            code: $header['code'],
            playedAt: $header['date'],
            homeClubId: $home['clubId'],
            homeName: $home['name'],
            awayClubId: $away['clubId'],
            awayName: $away['name'],
            homeSets: $home['sets'],
            awaySets: $away['sets'],
            location: $header['location'],
            matchday: $header['matchday'],
            phase: $header['phase'],
            competition: $header['competition'],
        );
    }

    /**
     * @return array{matchCenterId: int|null, code: string|null, date: CarbonImmutable|null, location: string|null, matchday: int|null, phase: string|null, competition: string|null}
     */
    private function parseHeader(LvfDocument $document, DOMNode $table): array
    {
        $result = [
            'matchCenterId' => null,
            'code' => null,
            'date' => null,
            'location' => null,
            'matchday' => null,
            'phase' => null,
            'competition' => null,
        ];

        $day = null;
        $time = null;
        $candidates = [];

        foreach ($document->xpath->query('.//thead//th', $table) ?: [] as $cell) {
            $text = LvfDocument::text($cell);

            foreach ($document->xpath->query('.//a/@href', $cell) ?: [] as $href) {
                if (preg_match('#/match-center/(\d+)#', $href->nodeValue ?? '', $m) === 1) {
                    $result['matchCenterId'] = (int) $m[1];
                }
            }

            if ($text === '') {
                continue;
            }

            if (preg_match('/^#(\S+)$/u', $text, $m) === 1) {
                $result['code'] = $m[1];

                continue;
            }

            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $text, $m) === 1) {
                $day = $m;

                continue;
            }

            if (preg_match('/^(\d{1,2}):(\d{2})$/', $text, $m) === 1) {
                $time = $m;

                continue;
            }

            if (preg_match('/(\d+)\s*a?\s*Giornata\s*-\s*(Andata|Ritorno)/ui', $text, $m) === 1) {
                $result['matchday'] = (int) $m[1];
                $result['phase'] = ucfirst(mb_strtolower($m[2]));

                $competition = trim(mb_substr($text, 0, (int) mb_strpos($text, $m[0])));
                $result['competition'] = $competition !== '' ? $competition : null;

                continue;
            }

            // Quel che resta e non è il link al Match Center è l'impianto.
            if (mb_stripos($text, 'match center') === false) {
                $candidates[] = $text;
            }
        }

        if ($day !== null) {
            $result['date'] = CarbonImmutable::create(
                (int) $day[3], (int) $day[2], (int) $day[1],
                $time !== null ? (int) $time[1] : 0,
                $time !== null ? (int) $time[2] : 0,
            );
        }

        $result['location'] = $candidates[0] ?? null;

        return $result;
    }

    /**
     * @return list<array{clubId: int, name: string, sets: int|null}>
     */
    private function parseTeams(LvfDocument $document, DOMNode $table): array
    {
        $teams = [];

        foreach ($document->xpath->query('.//tbody/tr', $table) ?: [] as $row) {
            $link = $document->xpath->query(".//a[contains(@href, '/club/')]", $row)?->item(0);

            if (! $link instanceof DOMElement) {
                continue;
            }

            $clubId = LvfDocument::clubIdFromHref($link->getAttribute('href'));
            $name = LvfDocument::text($link);

            if ($clubId === null || $name === '') {
                continue;
            }

            // Presente solo sulla pagina risultati; sul calendario le gare non
            // hanno ancora un punteggio e la cella non viene emessa affatto.
            $setsCell = $document->xpath->query(".//th[contains(@class, 'num')]", $row)?->item(0);
            $setsText = LvfDocument::text($setsCell);

            $teams[] = [
                'clubId' => $clubId,
                'name' => $name,
                'sets' => is_numeric($setsText) ? (int) $setsText : null,
            ];
        }

        return $teams;
    }
}
