<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sei voci di menu portavano a una pagina che non esiste.
 *
 * Quattro sono i documenti di governance nel footer — Protocollo Razzismo,
 * Protocollo Bullismo, Codice Tutela Minori, Modello Organizzativo: puntavano
 * a pagine mai create. Quei documenti stanno sulla pagina Safeguarding, che ora
 * ne permette anche il caricamento in PDF: è lì che vanno mandati.
 *
 * "Iscrizione (Experience)" puntava a una pagina che nel seeder esiste ma con
 * un indirizzo di esempio nel pulsante: si manda alla sezione Summer Camp, che
 * esiste davvero.
 *
 * "Magazine" invece la pagina se la merita: il modello sa già gestire le
 * edizioni in PDF ed è ciò che la redazione ha chiesto di poter caricare.
 * Viene creata vuota, senza edizioni di esempio.
 */
return new class extends Migration
{
    /** Voce di menu => destinazione corretta. */
    private const REINDIRIZZI = [
        '/protocollo-razzismo' => '/societa/safeguarding',
        '/protocollo-bullismo' => '/societa/safeguarding',
        '/codice-tutela-minori' => '/societa/safeguarding',
        '/modello-organizzativo' => '/societa/safeguarding',
        '/summer-camp/iscrizione/' => '/summer-camp/',
    ];

    public function up(): void
    {
        foreach (self::REINDIRIZZI as $vecchio => $nuovo) {
            DB::table('menu_items')
                ->whereIn('url', [$vecchio, rtrim($vecchio, '/'), $vecchio.'/'])
                ->update(['url' => $nuovo]);
        }

        $this->creaLaPaginaMagazine();
    }

    public function down(): void
    {
        // Le destinazioni precedenti erano rotte: ripristinarle non è un
        // rollback utile. La pagina Magazine resta, non fa danno.
    }

    private function creaLaPaginaMagazine(): void
    {
        if (DB::table('pages')->where('slug', 'magazine')->exists()) {
            return;
        }

        $titolo = ['it' => 'Magazine', 'en' => 'Magazine'];

        $descrizione = [
            'it' => "Sfoglia l'archivio del magazine ufficiale della Savino Del Bene Volley. Scarica le edizioni in formato PDF.",
            'en' => 'Browse the archive of the official Savino Del Bene Volley magazine. Download the issues as PDF.',
        ];

        $contenuto = [
            'it' => '<h2>Archivio Magazine</h2><p>In questa sezione puoi consultare e scaricare in formato PDF tutti i numeri di "Double Face", il magazine ufficiale della Savino Del Bene Volley.</p>',
            'en' => '<h2>Magazine Archive</h2><p>Here you can read and download every issue of "Double Face", the official Savino Del Bene Volley magazine, as a PDF.</p>',
        ];

        // Nessuna edizione di esempio: un riquadro che sembra scaricabile e non
        // lo è è peggio di una sezione che dice di non avere ancora nulla.
        $datiPagina = ['it' => ['magazines' => []], 'en' => ['magazines' => []]];

        DB::table('pages')->insert([
            'title' => json_encode($titolo, JSON_UNESCAPED_UNICODE),
            'slug' => 'magazine',
            'template' => 'Public/ContentPage',
            'meta_description' => json_encode($descrizione, JSON_UNESCAPED_UNICODE),
            'content' => json_encode($contenuto, JSON_UNESCAPED_UNICODE),
            'content_data' => json_encode($datiPagina, JSON_UNESCAPED_UNICODE),
            'status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
