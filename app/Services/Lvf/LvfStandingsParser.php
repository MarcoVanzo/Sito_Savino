<?php

namespace App\Services\Lvf;

use App\Services\Lvf\Data\LvfStandingRow;
use DOMElement;
use DOMNode;

/**
 * Estrae la classifica dalla pagina `/classifica/` della Lega.
 *
 * La pagina contiene due tabelle: la classifica vera e la griglia degli scontri
 * diretti (`griglia-classifica`), che va ignorata. Poiché il nome della seconda
 * contiene quello della prima come sottostringa, il selettore confronta il token
 * di classe per intero.
 *
 * Le colonne sono individuate leggendo le intestazioni, non per posizione: la
 * Lega mostra un sottoinsieme ridotto su mobile e potrebbe riordinarle.
 */
class LvfStandingsParser
{
    /**
     * Etichetta di colonna => campo del DTO.
     */
    private const COLUMNS = [
        'PT' => 'points',
        'G' => 'played',
        'V' => 'won',
        'P' => 'lost',
        '3-0' => 'won30',
        '3-1' => 'won31',
        '3-2' => 'won32',
        '2-3' => 'lost23',
        '1-3' => 'lost13',
        '0-3' => 'lost03',
        'SV' => 'setsWon',
        'SP' => 'setsLost',
        'PF' => 'pointsFor',
        'PS' => 'pointsAgainst',
        'QS' => 'setRatio',
        'QP' => 'pointRatio',
    ];

    /**
     * @return list<LvfStandingRow>
     */
    public function parse(string $html): array
    {
        $document = LvfDocument::fromHtml($html);

        $table = $document->xpath->query(
            "//table[contains(concat(' ', normalize-space(@class), ' '), ' classifica ')]"
        )?->item(0);

        if (! $table instanceof DOMNode) {
            return [];
        }

        $columns = $this->mapColumns($document, $table);
        $rows = [];

        foreach ($document->xpath->query('.//tbody/tr', $table) ?: [] as $row) {
            $parsed = $this->parseRow($document, $row, $columns);

            if ($parsed instanceof LvfStandingRow) {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, int> etichetta => indice di cella
     */
    private function mapColumns(LvfDocument $document, DOMNode $table): array
    {
        $columns = [];
        $index = 0;

        foreach ($document->xpath->query('.//thead//th', $table) ?: [] as $cell) {
            $columns[LvfDocument::text($cell)] = $index++;
        }

        return $columns;
    }

    /**
     * @param  array<string, int>  $columns
     */
    private function parseRow(LvfDocument $document, DOMNode $row, array $columns): ?LvfStandingRow
    {
        $cells = [];

        foreach ($document->xpath->query('./th | ./td', $row) ?: [] as $cell) {
            $cells[] = $cell;
        }

        if ($cells === []) {
            return null;
        }

        $link = $document->xpath->query(".//a[contains(@href, '/club/')]", $row)?->item(0);

        if (! $link instanceof DOMElement) {
            return null;
        }

        $clubId = LvfDocument::clubIdFromHref($link->getAttribute('href'));
        $teamName = LvfDocument::text($link);

        if ($clubId === null || $teamName === '') {
            return null;
        }

        $position = (int) LvfDocument::text($cells[0]);

        if ($position <= 0) {
            return null;
        }

        $value = function (string $label) use ($cells, $columns): ?string {
            $index = $columns[$label] ?? null;

            return $index !== null && isset($cells[$index])
                ? LvfDocument::text($cells[$index])
                : null;
        };

        $numbers = [];

        foreach (self::COLUMNS as $label => $field) {
            $raw = $value($label);

            // I due rapporti sono decimali e possono mancare; il resto sono
            // conteggi, che a colonna vuota valgono zero.
            if (in_array($field, ['setRatio', 'pointRatio'], true)) {
                $numbers[$field] = is_numeric($raw) ? (float) $raw : null;

                continue;
            }

            $numbers[$field] = (int) $raw;
        }

        return new LvfStandingRow(
            position: $position,
            clubId: $clubId,
            teamName: $teamName,
            points: $numbers['points'],
            played: $numbers['played'],
            won: $numbers['won'],
            lost: $numbers['lost'],
            won30: $numbers['won30'],
            won31: $numbers['won31'],
            won32: $numbers['won32'],
            lost23: $numbers['lost23'],
            lost13: $numbers['lost13'],
            lost03: $numbers['lost03'],
            setsWon: $numbers['setsWon'],
            setsLost: $numbers['setsLost'],
            pointsFor: $numbers['pointsFor'],
            pointsAgainst: $numbers['pointsAgainst'],
            setRatio: $numbers['setRatio'],
            pointRatio: $numbers['pointRatio'],
        );
    }
}
