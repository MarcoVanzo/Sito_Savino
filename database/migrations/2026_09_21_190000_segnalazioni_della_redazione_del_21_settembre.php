<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Due segnalazioni della redazione del 21 settembre 2026.
 *
 * 1. Il passaggio di una societa' da Partner Ufficiale a Societa' Affiliata
 *    era stato salvato tre volte senza che il sito cambiasse: il pannello era
 *    in inglese e `content_data` e' tradotto in blocco, quindi la modifica e'
 *    finita nella sola scheda inglese. Da qui in avanti gli elenchi senza
 *    traduzione si scrivono in tutte le lingue
 *    (`ContentData::CHIAVI_COMUNI`); qui si porta all'italiano il lavoro gia'
 *    fatto, altrimenti il primo salvataggio in italiano lo cancellerebbe.
 *
 * 2. La pagina Sponsor prometteva "Contattaci per una proposta" e apriva una
 *    mail all'indirizzo generale del sito, perche' `contact_email` era vuoto e
 *    il template ripiega sull'email di contatto. Le richieste di
 *    sponsorizzazione vanno a marketing@. Con l'occasione i numeri d'impatto
 *    passano a quelli comunicati dalla societa': i follower al posto delle
 *    impressioni, 40K spettatori, e niente piu' eventi annuali.
 *
 * Ogni correzione ha una guardia: tocca un valore solo se e' ancora quello
 * letto in produzione oggi. Rilanciarla non cambia nulla.
 */
return new class extends Migration
{
    /** I numeri d'impatto di oggi e quelli chiesti dalla redazione. */
    private const STATISTICHE = [
        'it' => [
            'stat1_label' => ['Impressioni Social', 'Followers Social'],
            'stat1_value' => ['2M+', '1.6M+'],
            'stat2_value' => ['50K+', '40K+'],
            'stat3_label' => ['Eventi Annuali', null],
            'stat3_value' => ['100+', null],
        ],
        'en' => [
            'stat1_label' => ['Social Impressions', 'Social Followers'],
            'stat3_label' => ['Annual Events', null],
        ],
    ];

    public function up(): void
    {
        $this->affiliazioniDallInglese();
        $this->dati('sponsor', fn (array $v, string $l): array => $this->sponsor($v, $l));

        CachePublicResponse::flush();
    }

    /**
     * Correzioni di contenuti: tornare indietro ripubblicherebbe i valori
     * sbagliati.
     */
    public function down(): void {}

    // ------------------------------------------------------------------
    // Affiliazioni
    // ------------------------------------------------------------------

    /**
     * Porta all'italiano l'elenco inglese, ma solo se le due copie contengono
     * davvero le stesse societa' con gli stessi link e gli stessi loghi: in
     * quel caso l'unica differenza e' il lavoro fatto nella scheda sbagliata
     * (livello e ordine). Se qualcuno ha aggiunto o tolto una societa' in una
     * sola delle due lingue, qui non si indovina: si lascia com'e'.
     */
    private function affiliazioniDallInglese(): void
    {
        $this->colonna('affiliazioni', 'content_data', function (array $lingue): array {
            $it = $lingue['it']['affiliates'] ?? null;
            $en = $lingue['en']['affiliates'] ?? null;

            if (! is_array($it) || ! is_array($en) || $it === $en) {
                return $lingue;
            }

            if ($this->stesseSocieta($it, $en)) {
                $lingue['it']['affiliates'] = $en;
            }

            return $lingue;
        });
    }

    /**
     * Le due copie hanno le stesse societa', con lo stesso sito e lo stesso
     * logo: cambiano solo il livello o l'ordine.
     *
     * @param  array<int, mixed>  $uno
     * @param  array<int, mixed>  $altro
     */
    private function stesseSocieta(array $uno, array $altro): bool
    {
        if (count($uno) !== count($altro)) {
            return false;
        }

        $impronta = static function (array $elenco): array {
            $righe = [];

            foreach ($elenco as $voce) {
                if (! is_array($voce) || ! isset($voce['name'])) {
                    return [];
                }

                $righe[] = implode('|', [
                    (string) $voce['name'],
                    (string) ($voce['url'] ?? ''),
                    (string) ($voce['logo'] ?? ''),
                ]);
            }

            sort($righe);

            return $righe;
        };

        $primo = $impronta($uno);

        return $primo !== [] && $primo === $impronta($altro);
    }

    // ------------------------------------------------------------------
    // Sponsor
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function sponsor(array $v, string $lingua): array
    {
        // Senza indirizzo il template usa quello generale del sito: le
        // richieste di sponsorizzazione arrivavano a info@.
        if (trim((string) ($v['contact_email'] ?? '')) === '') {
            $v['contact_email'] = 'marketing@savinodelbenevolley.it';
        }

        foreach (self::STATISTICHE[$lingua] ?? [] as $chiave => [$vecchio, $nuovo]) {
            if (($v[$chiave] ?? null) === $vecchio) {
                $v[$chiave] = $nuovo;
            }
        }

        return $v;
    }

    // ------------------------------------------------------------------
    // Attrezzi
    // ------------------------------------------------------------------

    /**
     * Applica la trasformazione a ogni lingua di `content_data`.
     */
    private function dati(string $slug, callable $trasforma): void
    {
        $this->colonna($slug, 'content_data', function (array $lingue) use ($trasforma): array {
            foreach ($lingue as $lingua => $valori) {
                if (is_array($valori) && ! array_is_list($valori)) {
                    $lingue[$lingua] = $trasforma($valori, (string) $lingua, $lingue);
                }
            }

            return $lingue;
        });
    }

    private function colonna(string $slug, string $colonna, callable $trasforma): void
    {
        foreach (DB::table('pages')->where('slug', $slug)->get(['id', $colonna]) as $pagina) {
            $lingue = json_decode((string) $pagina->{$colonna}, true);

            if (! is_array($lingue)) {
                continue;
            }

            $nuove = $trasforma($lingue);

            if ($nuove === $lingue) {
                continue;
            }

            DB::table('pages')->where('id', $pagina->id)->update([
                $colonna => json_encode($nuove, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            foreach (array_keys($nuove) as $lingua) {
                Cache::forget('public:page:'.$slug.':'.$lingua);
            }
        }
    }
};
