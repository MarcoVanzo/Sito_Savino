<?php

namespace App\Services\Cev;

use App\Services\Cev\Data\CevStandingRow;
use App\Services\Lvf\LvfDocument;
use DOMElement;
use DOMXPath;

/**
 * Legge le classifiche dei gironi dal portale della CEV.
 *
 * Una pagina contiene tutti i gironi della fase, ciascuno nel suo pannello
 * `Content_Left_<CID>` e nello stesso ordine delle schede con il nome. Le
 * colonne sono quelle della nostra tabella `standings`: giocate, vinte,
 * perse, ripartizione 3-0…0-3, punti, set e punti gioco con i quozienti.
 */
class CevStandingsParser
{
    /**
     * @return list<CevStandingRow>
     */
    public function parse(string $html): array
    {
        $xpath = LvfDocument::fromHtml($html)->xpath;

        $nomi = [];

        foreach ($xpath->query("//*[@id='ctl00_Content_Left_RadTabStripChampionship']//a[@title]") as $scheda) {
            if ($scheda instanceof DOMElement) {
                $nomi[] = trim($scheda->getAttribute('title'));
            }
        }

        $righe = [];

        foreach ($xpath->query("//div[starts-with(@id, 'Content_Left_') and contains(@class, 'rmpView')]") as $i => $pannello) {
            $girone = $nomi[$i] ?? '';

            foreach ($xpath->query(".//tr[.//*[contains(@id, '_Standing_teamName_')]]", $pannello) as $tr) {
                if ($tr instanceof DOMElement) {
                    $riga = $this->parseRow($xpath, $tr, $girone);

                    if ($riga !== null) {
                        $righe[] = $riga;
                    }
                }
            }
        }

        return $righe;
    }

    private function parseRow(DOMXPath $xpath, DOMElement $tr, string $girone): ?CevStandingRow
    {
        $celle = [];

        foreach ($xpath->query('./td', $tr) as $td) {
            $celle[] = LvfDocument::text($td);
        }

        // posizione, squadra, G V P, sei esiti, punti, set V P quoziente,
        // punti gioco fatti subiti quoziente, penalità
        if (count($celle) < 18 || $celle[1] === '') {
            return null;
        }

        $n = fn (int $i): int => (int) preg_replace('/\D/', '', $celle[$i]);

        return new CevStandingRow(
            girone: $girone,
            position: $n(0),
            teamName: $celle[1],
            played: $n(2),
            won: $n(3),
            lost: $n(4),
            won30: $n(5),
            won31: $n(6),
            won32: $n(7),
            lost23: $n(8),
            lost13: $n(9),
            lost03: $n(10),
            points: $n(11),
            setsWon: $n(12),
            setsLost: $n(13),
            setRatio: $this->quoziente($celle[14]),
            pointsFor: $n(15),
            pointsAgainst: $n(16),
            pointRatio: $this->quoziente($celle[17]),
        );
    }

    /**
     * "2,1429" con la virgola; "-" quando non si è ancora giocato.
     */
    private function quoziente(string $testo): float
    {
        $testo = str_replace(',', '.', $testo);

        return is_numeric($testo) ? round((float) $testo, 4) : 0.0;
    }
}
