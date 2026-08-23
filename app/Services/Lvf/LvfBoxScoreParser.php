<?php

namespace App\Services\Lvf;

use App\Services\Lvf\Data\LvfBoxScore;
use App\Services\Lvf\Data\LvfPlayerStat;
use DOMNode;

/**
 * Estrae il tabellino di una gara da `TabellinoGara_i.asp?IdGara=<id>`.
 *
 * La pagina è un vecchio ASP con tabelle annidate e intestazioni su due righe:
 * la prima raggruppa (PUNTI, BATTUTA, RICEZIONE, ATTACCO, MURO), la seconda
 * dettaglia le colonne. I `colspan` della prima riga NON sono affidabili — MURO
 * ne dichiara due ma ha una sola colonna — quindi l'allineamento si fa sulle
 * sotto-intestazioni effettive, assegnando a ogni gruppo al massimo tante
 * colonne quante ne dichiara e mai più di quelle rimaste.
 *
 * Le due tabelle delle giocatrici non contengono l'identificativo del club:
 * l'ordine è casa poi ospite, e gli id vengono passati da chi conosce la gara.
 */
class LvfBoxScoreParser
{
    /**
     * Le tabelle del tabellino sono annidate una dentro l'altra: `.//tr`
     * raccoglierebbe anche le righe delle tabelle interne, mescolando le
     * giocatrici con i parziali dei set. Servono le righe dirette.
     */
    private const DIRECT_ROWS = './tr | ./thead/tr | ./tbody/tr';

    /** Tutte le tabelle della pagina, a qualsiasi livello di annidamento. */
    private const ALL_TABLES = '//table';

    public function parse(string $html, int $lvfMatchId, int $homeClubId, int $awayClubId): LvfBoxScore
    {
        $document = LvfDocument::fromHtml($html);

        $players = [];
        $clubIds = [$homeClubId, $awayClubId];
        $index = 0;

        foreach ($document->xpath->query(self::ALL_TABLES) ?: [] as $table) {
            if (! $this->isPlayerTable($document, $table)) {
                continue;
            }

            $clubId = $clubIds[$index] ?? null;
            $index++;

            if ($clubId === null) {
                // Più di due tabelle giocatrici: non si sa a chi attribuirle.
                break;
            }

            foreach ($this->parsePlayerTable($document, $table, $clubId) as $player) {
                $players[] = $player;
            }
        }

        return new LvfBoxScore(
            lvfMatchId: $lvfMatchId,
            players: $players,
            sets: $this->parseSets($document),
            spectators: $this->parseSpectators($document),
            referees: $this->parseReferees($document),
        );
    }

    private function isPlayerTable(LvfDocument $document, DOMNode $table): bool
    {
        $rows = $document->xpath->query(self::DIRECT_ROWS, $table);

        if (($rows->length ?? 0) < 3) {
            return false;
        }

        $header = LvfDocument::text($rows->item(0));

        if (! str_contains($header, 'PUNTI') || ! str_contains($header, 'BATTUTA')) {
            return false;
        }

        // Ogni tabella delle giocatrici è avvolta da una o più tabelle-contenitore
        // che ripetono la stessa intestazione: contare le righe non basta a
        // distinguerle, perché anche il contenitore può averne diverse. Solo la
        // tabella vera porta la seconda riga con le sotto-intestazioni.
        //
        // Senza questa distinzione il contenitore consuma l'identificativo del
        // club destinato alla tabella successiva e le giocatrici finiscono
        // attribuite alla squadra avversaria.
        $subHeaders = $this->cellTexts($document, $rows->item(1));

        return in_array('Tot', $subHeaders, true) && in_array('BP', $subHeaders, true);
    }

    /**
     * @return list<LvfPlayerStat>
     */
    private function parsePlayerTable(LvfDocument $document, DOMNode $table, int $clubId): array
    {
        $rows = [];

        foreach ($document->xpath->query(self::DIRECT_ROWS, $table) ?: [] as $row) {
            $rows[] = $row;
        }

        if (count($rows) < 3) {
            return [];
        }

        $columns = $this->mapColumns($document, $rows[0], $rows[1]);
        $players = [];

        foreach (array_slice($rows, 2) as $row) {
            $cells = $this->cellTexts($document, $row);

            // Le righe di totali e di legenda non hanno il numero di maglia.
            if (count($cells) < 3 || ! is_numeric($cells[0])) {
                continue;
            }

            $player = $this->buildPlayer($cells, $columns, $clubId);

            if ($player instanceof LvfPlayerStat) {
                $players[] = $player;
            }
        }

        return $players;
    }

    /**
     * Associa (gruppo, sotto-intestazione) all'indice di colonna nelle righe dati.
     *
     * @return array<string, int> chiave "GRUPPO|etichetta"
     */
    private function mapColumns(LvfDocument $document, DOMNode $groupRow, DOMNode $labelRow): array
    {
        $groups = $this->cellsWithSpan($document, $groupRow);
        $labels = $this->cellTexts($document, $labelRow);

        // La prima cella di entrambe le righe copre numero di maglia e nome.
        array_shift($groups);
        array_shift($labels);

        $columns = [];
        $cursor = 0;
        $remaining = count($labels);

        foreach ($groups as [$name, $span]) {
            $take = max(0, min($span, $remaining - $cursor));

            for ($i = 0; $i < $take; $i++) {
                $label = $labels[$cursor + $i];
                // L'etichetta vuota è sempre il totale del gruppo.
                $key = $name.'|'.($label === '' ? 'Tot' : $label);
                // Le righe dati hanno numero e nome come prime due celle.
                $columns[$key] ??= $cursor + $i + 2;
            }

            $cursor += $take;
        }

        return $columns;
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int>  $columns
     */
    private function buildPlayer(array $cells, array $columns, int $clubId): ?LvfPlayerStat
    {
        $rawName = $cells[1] ?? '';

        if (trim($rawName) === '') {
            return null;
        }

        $isCaptain = (bool) preg_match('/\(\s*C\s*\)/i', $rawName);
        $isLibero = (bool) preg_match('/\(\s*L\s*\)/i', $rawName);
        $name = trim((string) preg_replace('/\(\s*[CL]\s*\)/i', '', $rawName));

        $value = function (string $group, string $label) use ($cells, $columns): ?string {
            $index = $columns[$group.'|'.$label] ?? null;

            return $index !== null ? ($cells[$index] ?? null) : null;
        };

        return new LvfPlayerStat(
            clubId: $clubId,
            playerName: $name,
            jerseyNumber: is_numeric($cells[0]) ? (int) $cells[0] : null,
            isCaptain: $isCaptain,
            isLibero: $isLibero,
            setsPlayed: $this->countSetsPlayed($cells, $columns),
            pointsTotal: $this->int($value('PUNTI', 'Tot')),
            pointsBreak: $this->int($value('PUNTI', 'BP')),
            pointsWinLoss: $this->int($value('PUNTI', 'VP')),
            serveTotal: $this->int($value('BATTUTA', 'Tot')),
            serveErrors: $this->int($value('BATTUTA', 'Err')),
            servePoints: $this->int($value('BATTUTA', 'Pt')),
            receptionTotal: $this->int($value('RICEZIONE', 'Tot')),
            receptionErrors: $this->int($value('RICEZIONE', 'Err')),
            receptionPositivePct: $this->percent($value('RICEZIONE', 'Pos%')),
            receptionPerfectPct: $this->percent($value('RICEZIONE', 'Prf%')),
            attackTotal: $this->int($value('ATTACCO', 'Tot')),
            attackErrors: $this->int($value('ATTACCO', 'Err')),
            attackBlocked: $this->int($value('ATTACCO', 'Mur')),
            attackPoints: $this->int($value('ATTACCO', 'Pt')),
            attackPct: $this->percent($value('ATTACCO', 'Pt%')),
            blockPoints: $this->int($value('MURO', 'Pt')),
        );
    }

    /**
     * Nelle colonne per set c'è la rotazione iniziale (un numero) o `*` se
     * l'atleta è entrata a gara in corso; vuoto significa mai scesa in campo.
     *
     * @param  list<string>  $cells
     * @param  array<string, int>  $columns
     */
    private function countSetsPlayed(array $cells, array $columns): int
    {
        $played = 0;

        foreach (range(1, 5) as $set) {
            $index = $columns['SET|'.$set] ?? null;

            if ($index === null) {
                continue;
            }

            $value = trim((string) ($cells[$index] ?? ''));

            if ($value !== '' && $value !== '.') {
                $played++;
            }
        }

        return $played;
    }

    /**
     * @return list<array{set: int, duration: int|null, partials: list<string>}>
     */
    private function parseSets(LvfDocument $document): array
    {
        foreach ($document->xpath->query(self::ALL_TABLES) ?: [] as $table) {
            $rows = $document->xpath->query(self::DIRECT_ROWS, $table);

            if (! $this->eLaTabellaDeiSet($document, $rows)) {
                continue;
            }

            $sets = [];

            foreach ($rows as $position => $row) {
                if ($position === 0) {
                    continue;
                }

                $set = $this->rigaDelSet($document, $row);

                if ($set !== null) {
                    $sets[] = $set;
                }
            }

            return $sets;
        }

        return [];
    }

    /**
     * La tabella dei set si riconosce dalla testata "Set … Parziali".
     *
     * Il confronto e' sulle celle esatte, non sul testo: la tabella-contenitore
     * piu' esterna ha una sola riga che ingloba l'intero documento e
     * supererebbe qualunque controllo per sottostringa.
     */
    private function eLaTabellaDeiSet(LvfDocument $document, ?\DOMNodeList $rows): bool
    {
        if (($rows->length ?? 0) < 2) {
            return false;
        }

        $header = $this->cellTexts($document, $rows->item(0));

        return ($header[0] ?? '') === 'Set' && in_array('Parziali', $header, true);
    }

    /**
     * Una riga della tabella dei set, se e' davvero un set e non un'intestazione.
     *
     * @return array{set: int, duration: int|null, partials: list<string>}|null
     */
    private function rigaDelSet(LvfDocument $document, DOMNode $row): ?array
    {
        $cells = $this->cellTexts($document, $row);

        if (count($cells) < 2 || ! is_numeric($cells[0])) {
            return null;
        }

        $partials = array_values(array_filter(
            array_slice($cells, 2),
            fn (string $cell) => preg_match('/^\d+-\d+$/', $cell) === 1
        ));

        return [
            'set' => (int) $cells[0],
            'duration' => preg_match('/(\d+)/', $cells[1], $m) === 1 ? (int) $m[1] : null,
            'partials' => $partials,
        ];
    }

    /**
     * Gli spettatori stanno in una testata (Data | Ora | Spettatori | Incasso)
     * con i valori nella riga sotto, alla stessa colonna.
     */
    private function parseSpectators(LvfDocument $document): ?int
    {
        foreach ($document->xpath->query(self::ALL_TABLES) ?: [] as $table) {
            $rows = $document->xpath->query(self::DIRECT_ROWS, $table);

            if (($rows->length ?? 0) < 2) {
                continue;
            }

            $header = $this->cellTexts($document, $rows->item(0));
            $position = array_search('Spettatori', $header, true);

            if ($position === false) {
                continue;
            }

            $value = $this->cellTexts($document, $rows->item(1))[$position] ?? '';
            // Il separatore delle migliaia è il punto: "1.444".
            $digits = (string) preg_replace('/\D/', '', $value);

            return $digits !== '' ? (int) $digits : null;
        }

        return null;
    }

    /**
     * Gli arbitri stanno invece su una riga a due celle: etichetta e valore.
     */
    private function parseReferees(LvfDocument $document): ?string
    {
        foreach ($document->xpath->query('//tr') ?: [] as $row) {
            $cells = $this->cellTexts($document, $row);
            $position = array_search('Arbitri', $cells, true);

            if ($position === false) {
                continue;
            }

            $value = $cells[$position + 1] ?? '';

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function cellTexts(LvfDocument $document, DOMNode $row): array
    {
        $cells = [];

        foreach ($document->xpath->query('./th | ./td', $row) ?: [] as $cell) {
            $cells[] = str_replace("\u{00A0}", ' ', LvfDocument::text($cell));
        }

        return array_map('trim', $cells);
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function cellsWithSpan(LvfDocument $document, DOMNode $row): array
    {
        $cells = [];

        foreach ($document->xpath->query('./th | ./td', $row) ?: [] as $cell) {
            $span = 1;

            if ($cell instanceof \DOMElement && $cell->hasAttribute('colspan')) {
                $span = max(1, (int) $cell->getAttribute('colspan'));
            }

            $cells[] = [LvfDocument::text($cell), $span];
        }

        return $cells;
    }

    /**
     * Il tabellino usa il punto per «nessun dato».
     */
    private function int(?string $value): int
    {
        $value = trim((string) $value);

        return is_numeric($value) ? (int) $value : 0;
    }

    private function percent(?string $value): ?int
    {
        $value = trim((string) $value);

        return preg_match('/(-?\d+)\s*%/', $value, $m) === 1 ? (int) $m[1] : null;
    }
}
