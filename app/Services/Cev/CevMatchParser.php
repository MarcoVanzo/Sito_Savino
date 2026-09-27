<?php

namespace App\Services\Cev;

use App\Services\Cev\Data\CevMatch;
use App\Services\Lvf\LvfDocument;
use Carbon\CarbonImmutable;
use DOMElement;
use DOMXPath;

/**
 * Legge le pagine del portale CEV: le fasi di una competizione e le gare di
 * ciascuna fase.
 *
 * Il portale è un ASP.NET con controlli Telerik: niente classi utili, ma gli
 * id dei controlli hanno suffissi stabili (`_LB_FederationMatchNumber`,
 * `_LB_SetCasa`, `_LB_DataOra`…) ed è su quelli che si aggancia il parser.
 * Ogni girone di una fase è un pannello `Content_Left_<CID>`, nello stesso
 * ordine delle schede con il nome del girone.
 */
class CevMatchParser
{
    /**
     * @param  array<string, list<string>>  $fusiOrari  fuso => città (vedi services.cev.fusi_orari)
     */
    public function __construct(
        private readonly array $fusiOrari = [],
        private readonly string $fusoDelSito = 'Europe/Rome',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (array) config('services.cev.fusi_orari', []),
            (string) config('app.timezone', 'Europe/Rome'),
        );
    }

    /**
     * Identificativi delle fasi, nell'ordine in cui il portale le elenca.
     *
     * @return list<int>
     */
    public function phaseIds(string $html, int $competitionId): array
    {
        preg_match_all('/Competition\.aspx\?ID='.$competitionId.'&(?:amp;)?PID=(\d+)/', $html, $m);

        return array_values(array_unique(array_map('intval', $m[1])));
    }

    /**
     * Anno e sesso dell'edizione, dal titolo della pagina ("CEV Champions
     * League Volley 2027 | Women"). Servono a non importare l'edizione
     * sbagliata: l'ID 1947, uno meno della femminile 2027, è la maschile.
     *
     * @return array{anno: int|null, femminile: bool}
     */
    public function edizione(string $html): array
    {
        if (preg_match('/Champions League Volley (\d{4}) \| (Women|Men)/', $html, $m) !== 1) {
            return ['anno' => null, 'femminile' => false];
        }

        return ['anno' => (int) $m[1], 'femminile' => $m[2] === 'Women'];
    }

    /**
     * @return list<CevMatch>
     */
    public function parse(string $html): array
    {
        $document = LvfDocument::fromHtml($html);
        $xpath = $document->xpath;

        $fase = LvfDocument::text($xpath->query(
            "//*[@id='ctl00_Content_Left_RadTabStripPhase']//a[contains(@class,'rtsSelected')]"
        )->item(0));

        $matches = [];

        foreach ($this->gironi($xpath) as [$pannello, $girone]) {
            $righe = $xpath->query(".//*[substring(@id, string-length(@id) - 9) = '_div_match']", $pannello);

            foreach ($righe as $riga) {
                if ($riga instanceof DOMElement) {
                    $match = $this->parseRow($xpath, $riga, $girone !== '' ? $girone : $fase);

                    if ($match !== null) {
                        $matches[$match->cevMatchId] = $match;
                    }
                }
            }
        }

        return array_values($matches);
    }

    /**
     * Coppie (pannello, nome del girone o del turno).
     *
     * @return list<array{0: DOMElement, 1: string}>
     */
    private function gironi(DOMXPath $xpath): array
    {
        $nomi = [];

        foreach ($xpath->query("//*[@id='ctl00_Content_Left_RadTabStripChampionship']//a[@title]") as $scheda) {
            if ($scheda instanceof DOMElement) {
                $nomi[] = trim($scheda->getAttribute('title'));
            }
        }

        $gironi = [];

        foreach ($xpath->query("//div[starts-with(@id, 'Content_Left_') and contains(@class, 'rmpView')]") as $i => $pannello) {
            if ($pannello instanceof DOMElement) {
                $gironi[] = [$pannello, $nomi[$i] ?? ''];
            }
        }

        return $gironi;
    }

    private function parseRow(DOMXPath $xpath, DOMElement $riga, string $girone): ?CevMatch
    {
        $href = $this->attr($xpath, ".//a[contains(@href, 'mID=')]", 'href', $riga);

        if (preg_match('/mID=(\d+)/', $href, $m) !== 1) {
            return null;
        }

        $giornata = LvfDocument::text($xpath->query(
            "ancestor::fieldset[1]//*[substring(@id, string-length(@id) - 9) = 'LB_LegName']",
            $riga
        )->item(0));

        [$phase, $matchday] = $this->faseEGiornata($girone, $giornata);

        $location = $this->campo($xpath, $riga, 'LB_Palasport');
        $casa = $this->campo($xpath, $riga, 'Label2');

        return new CevMatch(
            cevMatchId: (int) $m[1],
            code: $this->campo($xpath, $riga, 'LB_FederationMatchNumber'),
            homeTeamId: $this->teamId($this->attr($xpath, ".//img[substring(@id, string-length(@id) - 6) = '_Image2']", 'src', $riga)),
            homeName: $casa,
            awayTeamId: $this->teamId($this->attr($xpath, ".//img[substring(@id, string-length(@id) - 6) = '_Image3']", 'src', $riga)),
            awayName: $this->campo($xpath, $riga, 'Label4'),
            playedAt: $this->dataOra($this->campo($xpath, $riga, 'LB_DataOra'), $location, $casa),
            location: $location !== '' ? $location : null,
            phase: $phase,
            matchday: $matchday,
            homeSets: $this->numero($this->campo($xpath, $riga, 'LB_SetCasa')),
            awaySets: $this->numero($this->campo($xpath, $riga, 'LB_SetOspiti')),
        );
    }

    /**
     * Nei gironi le giornate sono "Leg 1".."Leg 6"; nei turni a eliminazione
     * sono "Home Matches" e "Away Matches" (andata e ritorno) e nella Final
     * Four "Semi Finals", "Bronze Medal Match", "Gold Medal Match". Solo le
     * prime sono numeri di giornata: le altre diventano parte della fase.
     *
     * @return array{0: string, 1: int|null}
     */
    private function faseEGiornata(string $girone, string $giornata): array
    {
        if (preg_match('/^Leg\s+(\d+)$/i', $giornata, $m) === 1) {
            return [$girone, (int) $m[1]];
        }

        return [$giornata !== '' ? "{$girone} · {$giornata}" : $girone, null];
    }

    /**
     * Il portale scrive l'ora locale del palazzetto. "Match not yet
     * scheduled" e il segnaposto "01/01/2100 00:00" valgono data non fissata.
     */
    private function dataOra(string $testo, string $impianto, string $squadraDiCasa): ?CarbonImmutable
    {
        if (preg_match('#(\d{2})/(\d{2})/(\d{4})\s+(\d{1,2}):(\d{2})#', $testo, $m) !== 1 || (int) $m[3] >= 2100) {
            return null;
        }

        // Senza impianto (capita a calendario appena pubblicato) la città si
        // legge dal nome della squadra di casa: "Eczacibasi Peron ISTANBUL".
        $fuso = $this->fusoDellaCitta($impianto) ?? $this->fusoDellaCitta($squadraDiCasa) ?? $this->fusoDelSito;

        $locale = CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1], (int) $m[4], (int) $m[5], 0, $fuso);

        return $locale?->setTimezone($this->fusoDelSito);
    }

    /**
     * Il nome dell'impianto (e della squadra) finisce con la città in
     * maiuscolo ("Vakifbank Spor Sarayi ISTANBUL"): da lì si risale al fuso.
     * Null quando la città non è fra quelle di `services.cev.fusi_orari`.
     */
    private function fusoDellaCitta(string $impianto): ?string
    {
        $impianto = mb_strtoupper(trim($impianto));

        if ($impianto === '') {
            return null;
        }

        foreach ($this->fusiOrari as $fuso => $citta) {
            foreach ($citta as $nome) {
                $nome = mb_strtoupper($nome);

                if ($impianto === $nome || str_ends_with($impianto, ' '.$nome) || str_contains($impianto, ' '.$nome.' ')) {
                    return $fuso;
                }
            }
        }

        return null;
    }

    private function campo(DOMXPath $xpath, DOMElement $riga, string $suffisso): string
    {
        $lunghezza = strlen($suffisso);

        return LvfDocument::text($xpath->query(
            ".//*[substring(@id, string-length(@id) - {$lunghezza}) = '_{$suffisso}']",
            $riga
        )->item(0));
    }

    private function attr(DOMXPath $xpath, string $query, string $attributo, DOMElement $contesto): string
    {
        $nodo = $xpath->query($query, $contesto)->item(0);

        return $nodo instanceof DOMElement ? $nodo->getAttribute($attributo) : '';
    }

    private function teamId(string $src): int
    {
        return preg_match('/[?&]ID=(\d+)/', $src, $m) === 1 ? (int) $m[1] : 0;
    }

    private function numero(string $testo): ?int
    {
        return preg_match('/^\d+$/', $testo) === 1 ? (int) $testo : null;
    }
}
