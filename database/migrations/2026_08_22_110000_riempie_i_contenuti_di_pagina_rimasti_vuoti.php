<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rimette nel database i testi che il sito mostrava dalle traduzioni.
 *
 * La verifica sui dati veri ha trovato due pagine che online sembravano a
 * posto e nel pannello erano vuote:
 *
 *  - `sponsor` non aveva la copia inglese di `content_data`, quindi
 *    `/en/sponsor` ripiegava sull'italiano e mostrava "Diventa Partner" e
 *    "Impressioni Social" a un lettore inglese;
 *  - `title-sponsor` aveva `content_data` uguale alla stringa `{"en": ""}` —
 *    uno scalare al posto dell'array — e cadeva sui testi di ripiego cablati
 *    nei componenti, che la redazione non trova da nessuna parte.
 *
 * La migrazione del 21 agosto riempiva le chiavi vuote dai valori iniziali del
 * template, ma i modelli Sponsor, Talent Day e Contatti non ne avevano ancora
 * e uno scalare le faceva saltare la pagina. Qui si fa tutte e due le cose, e
 * si assegna una descrizione SEO alle pagine pubblicate che non ne avevano:
 * senza, Google si sceglie da sé una frase presa dal corpo della pagina.
 */
return new class extends Migration
{
    /**
     * Descrizione per i motori di ricerca delle pagine che ne erano prive.
     * Una per pagina: la stessa frase ripetuta vale quanto non averla.
     */
    private const DESCRIZIONI = [
        'home' => [
            'it' => 'Savino Del Bene Volley: calendario, risultati, classifica, rosa e biglietti della squadra di Serie A1 femminile di Scandicci.',
            'en' => 'Savino Del Bene Volley: fixtures, results, standings, squad and tickets of the Serie A1 women\'s volleyball team from Scandicci.',
        ],
        'societa' => [
            'it' => 'La Savino Del Bene Volley: storia, organigramma, palazzetto e safeguarding della società di Serie A1 femminile.',
            'en' => 'Savino Del Bene Volley: history, management, arena and safeguarding of the Serie A1 women\'s volleyball club.',
        ],
        'ticketing' => [
            'it' => 'Abbonamenti, biglietti, convenzioni e accessibilità per seguire la Savino Del Bene Volley al Pala BigMat.',
            'en' => 'Season passes, tickets, partner rates and accessibility for Savino Del Bene Volley matches at Pala BigMat.',
        ],
        'sponsor' => [
            'it' => 'Gli sponsor e i partner della Savino Del Bene Volley e le opportunità per associare il proprio marchio alla squadra.',
            'en' => 'The sponsors and partners of Savino Del Bene Volley and the opportunities to associate your brand with the team.',
        ],
        'youth' => [
            'it' => 'Il settore giovanile della Savino Del Bene Volley: squadre, allenatori, scouting e Talent Day.',
            'en' => 'The Savino Del Bene Volley youth academy: teams, coaches, scouting and Talent Day.',
        ],
        'sociale' => [
            'it' => 'I progetti sociali della Savino Del Bene Volley: Volley 4 All, progetto scuola, sostenibilità e aste benefiche.',
            'en' => 'The social projects of Savino Del Bene Volley: Volley 4 All, school programme, sustainability and charity auctions.',
        ],
        'comunicazione' => [
            'it' => 'Area stampa della Savino Del Bene Volley: accrediti, cartelle stampa, magazine e contatti dell\'ufficio comunicazione.',
            'en' => 'Savino Del Bene Volley press area: accreditation, press kits, magazine and press office contacts.',
        ],
        'shop' => [
            'it' => 'Lo shop ufficiale della Savino Del Bene Volley: kit gara, abbigliamento, accessori, outlet e aste.',
            'en' => 'The official Savino Del Bene Volley shop: match kit, apparel, accessories, outlet and auctions.',
        ],
        'privacy-policy' => [
            'it' => 'Informativa sul trattamento dei dati personali del sito della Savino Del Bene Volley.',
            'en' => 'Personal data processing policy for the Savino Del Bene Volley website.',
        ],
        'cookie-policy' => [
            'it' => 'Informativa sui cookie usati dal sito della Savino Del Bene Volley e su come gestirne il consenso.',
            'en' => 'Cookie policy of the Savino Del Bene Volley website and how to manage your consent.',
        ],
    ];

    public function up(): void
    {
        $iniziali = require database_path('data/page_template_defaults.php');
        $lingue = config('app.supported_locales', ['it', 'en']);

        foreach (DB::table('pages')->select('id', 'slug', 'template', 'content_data', 'meta_description')->get() as $pagina) {
            $modifiche = [];

            // Il confronto è con il valore in tabella, non con quello già
            // normalizzato: una pagina il cui `content_data` è ridotto a una
            // stringa va riscritta anche quando non c'è nessun valore
            // iniziale da aggiungere.
            $prima = json_decode((string) $pagina->content_data, true);
            $dati = $this->contentData($pagina->content_data, $lingue);

            foreach ($lingue as $lingua) {
                $valori = $iniziali[$pagina->template][$lingua]
                    ?? $iniziali[$pagina->template]['it']
                    ?? [];

                foreach ($valori as $chiave => $valore) {
                    if (empty($dati[$lingua][$chiave]) && ! empty($valore)) {
                        $dati[$lingua][$chiave] = $valore;
                    }
                }
            }

            if ($dati !== $prima) {
                $modifiche['content_data'] = json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $descrizione = $this->metaDescription($pagina, $lingue);

            if ($descrizione !== null) {
                $modifiche['meta_description'] = $descrizione;
            }

            if ($modifiche !== []) {
                DB::table('pages')->where('id', $pagina->id)->update($modifiche);
            }
        }
    }

    /**
     * `content_data` come array per lingua, qualunque cosa ci sia in tabella.
     *
     * Salvando dal pannello una pagina è finita con `{"en": ""}`: una stringa
     * dove il resto del codice si aspetta un array. Riempirla chiave per
     * chiave darebbe "Cannot use a scalar value as an array", quindi le lingue
     * non valide si azzerano e si ripopolano dai valori iniziali.
     *
     * @param  array<int, string>  $lingue
     * @return array<string, array<string, mixed>>
     */
    private function contentData(?string $grezzo, array $lingue): array
    {
        $dati = json_decode((string) $grezzo, true);
        $dati = is_array($dati) ? $dati : [];

        foreach ($lingue as $lingua) {
            if (! is_array($dati[$lingua] ?? null)) {
                $dati[$lingua] = [];
            }
        }

        return $dati;
    }

    /**
     * La descrizione SEO da scrivere, o null se la pagina ne ha già una o non
     * è fra quelle previste.
     *
     * @param  array<int, string>  $lingue
     */
    private function metaDescription(object $pagina, array $lingue): ?string
    {
        if (! empty($pagina->meta_description) || ! isset(self::DESCRIZIONI[$pagina->slug])) {
            return null;
        }

        $testi = [];

        foreach ($lingue as $lingua) {
            $testi[$lingua] = self::DESCRIZIONI[$pagina->slug][$lingua]
                ?? self::DESCRIZIONI[$pagina->slug]['it'];
        }

        return json_encode($testi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Non reversibile: togliere questi testi rimetterebbe le pagine com'erano
     * quando il sito mostrava contenuti che in redazione non esistevano.
     */
    public function down(): void {}
};
