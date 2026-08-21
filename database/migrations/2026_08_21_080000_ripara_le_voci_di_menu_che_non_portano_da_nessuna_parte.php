<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tre voci di menu non portavano da nessuna parte.
 *
 * Vengono dall'import del vecchio sito, dove erano "link personalizzati" di
 * WordPress: l'importazione ne ha messo l'indirizzo nello slug della pagina
 * invece che nella voce di menu. In locale non si vedeva, perche' il database
 * di sviluppo e' seminato da zero; online:
 *
 * - "Title Sponsor" portava a /sponsor/title-sponsor, che rispondeva "pagina
 *   non trovata". La pagina esiste ma ha per slug l'indirizzo della Savino Del
 *   Bene Spa: era li' che la voce doveva portare.
 * - "Double Face" aveva per indirizzo `comunicazione/double-face/`, senza la
 *   barra iniziale: il browser lo leggeva come percorso relativo e da una
 *   pagina di sezione finiva su /comunicazione/comunicazione/double-face/.
 * - "Corporate Governance" e i suoi quattro documenti puntavano tutti alla
 *   pagina Safeguarding invece di aprire i rispettivi PDF.
 *
 * Le voci si riconoscono dall'indirizzo, non dall'identificativo: gli id di
 * sviluppo e quelli di produzione non coincidono.
 */
return new class extends Migration
{
    /**
     * Documento legale di ciascuna voce della colonna Corporate Governance.
     * La chiave e' l'etichetta italiana con cui la voce e' stata importata.
     */
    private const DOCUMENTI = [
        'Modello Organizzativo' => 'modello_organizzativo',
        'Codice Tutela Minori' => 'codice_tutela_minori',
        'Protocollo Bullismo' => 'protocollo_bullismo',
        'Protocollo Razzismo' => 'protocollo_razzismo',
    ];

    public function up(): void
    {
        $this->riparaTitleSponsor();
        $this->riparaDoubleFace();
        $this->collegaIDocumentiLegali();
    }

    /**
     * La voce apre il sito della Savino Del Bene Spa, come sul vecchio sito.
     * Alla pagina rimasta orfana si ridà uno slug leggibile: con un indirizzo
     * completo nello slug non e' raggiungibile ne' modificabile senza errori.
     */
    private function riparaTitleSponsor(): void
    {
        $pagina = DB::table('pages')->where('slug', 'like', 'http%savinodelbene.com%')->first();

        if (! $pagina) {
            return;
        }

        DB::table('menu_items')
            ->where('url', 'like', '%/title-sponsor%')
            ->update(['url' => rtrim($pagina->slug, '/').'/']);

        DB::table('pages')->where('id', $pagina->id)->update(['slug' => 'title-sponsor']);
    }

    /**
     * Slug di una sola parola e indirizzo assoluto: la sezione la mette gia'
     * la rotta (`/comunicazione/{slug}`).
     */
    private function riparaDoubleFace(): void
    {
        DB::table('pages')
            ->where('slug', 'comunicazione/double-face/')
            ->update(['slug' => 'double-face']);

        DB::table('menu_items')
            ->where('url', 'comunicazione/double-face/')
            ->update(['url' => '/comunicazione/double-face']);
    }

    /**
     * `documento:<chiave>` viene risolto da MenuItem::href() sul PDF caricato
     * in Impostazioni → Documenti Legali: sostituendo il file cambia il link,
     * e la voce sparisce finche' il documento non c'e'.
     */
    private function collegaIDocumentiLegali(): void
    {
        $voci = DB::table('menu_items')
            ->where('location', 'footer')
            ->whereIn('url', ['/societa/safeguarding', '#'])
            ->get(['id', 'label', 'url']);

        foreach ($voci as $voce) {
            // La colonna e' tradotta ma qualche riga storica contiene testo
            // semplice: leggerla con JSON_EXTRACT manderebbe MySQL in errore
            // (3141) e bloccherebbe l'avvio del container.
            $etichetta = $this->etichettaItaliana($voce->label);

            if (isset(self::DOCUMENTI[$etichetta])) {
                DB::table('menu_items')->where('id', $voce->id)
                    ->update(['url' => 'documento:'.self::DOCUMENTI[$etichetta]]);

                continue;
            }

            // Il titolo della colonna non ha un indirizzo suo: "#" non apre
            // nulla e il browser risale in cima alla pagina.
            if ($voce->url === '#') {
                DB::table('menu_items')->where('id', $voce->id)
                    ->update(['url' => '/societa/safeguarding']);
            }
        }
    }

    private function etichettaItaliana(?string $label): string
    {
        $decodificata = json_decode((string) $label, true);

        return is_array($decodificata)
            ? trim((string) ($decodificata['it'] ?? ''))
            : trim((string) $label);
    }

    /**
     * Non reversibile: ripristinare gli indirizzi rotti non ha senso.
     */
    public function down(): void {}
};
