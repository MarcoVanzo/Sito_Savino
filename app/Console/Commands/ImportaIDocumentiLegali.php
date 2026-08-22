<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Riporta i documenti legali dal sito precedente.
 *
 * Sono i PDF di Corporate Governance che il footer offre: modello
 * organizzativo, codice di tutela dei minori, protocollo bullismo, protocollo
 * razzismo, informativa fornitori. In archivio le impostazioni `legal.*` sono
 * vuote — i file erano spariti col passaggio a Spaces e la migrazione del 21
 * agosto ha azzerato i percorsi che non aprivano piu' niente.
 *
 * Le voci che puntano a un documento mancante spariscono dal menu, quindi non
 * si vedono link rotti; l'informativa fornitori invece ripiega su
 * `/informativa-fornitori`, che non e' una rotta e da' 404.
 *
 * I documenti sono ancora pubblicati sul sito precedente, che resta online:
 * si scaricano da li' invece di aspettare che qualcuno li ricarichi a mano.
 * L'abbinamento e' fatto sul testo del link della pagina Societa', non sul
 * nome del file: "Protocollo-1-Codice-di-condotta.pdf" e' il codice di tutela
 * dei minori, e indovinarlo dal nome avrebbe messo il documento sbagliato
 * sotto la voce sbagliata.
 */
class ImportaIDocumentiLegali extends Command
{
    protected $signature = 'documenti:importa-dal-vecchio-sito
        {--forza : Riscarica anche i documenti gia\' presenti}';

    protected $description = 'Riporta i PDF di Corporate Governance dal sito precedente';

    /**
     * Impostazione => indirizzo del PDF sul sito precedente.
     *
     * Privacy e cookie policy non ci sono: sul sito nuovo sono pagine del CMS,
     * non allegati. Le linee guida Safeguarding non hanno un'impostazione
     * propria — stanno nell'elenco `documents` della pagina omonima.
     */
    private const DOCUMENTI = [
        'legal.modello_organizzativo' => 'https://savinodelbenevolley.it/wp-content/uploads/2025/01/Modello-Organizzativo_compressed.pdf',
        'legal.codice_tutela_minori' => 'https://savinodelbenevolley.it/wp-content/uploads/2025/01/Protocollo-1-Codice-di-condotta.pdf',
        'legal.protocollo_bullismo' => 'https://savinodelbenevolley.it/wp-content/uploads/2025/01/Protocollo-2-Bullismo-e-cyberbullismo.pdf',
        'legal.protocollo_razzismo' => 'https://savinodelbenevolley.it/wp-content/uploads/2025/01/Protocollo-3-Razzismo-e-xenofobia.pdf',
        'legal.informativa_fornitori' => 'https://savinodelbenevolley.it/wp-content/uploads/2021/06/Informativa-Fornitori.pdf',
    ];

    /** La stessa cartella che usa il campo di upload del pannello. */
    private const CARTELLA = 'legal';

    public function handle(): int
    {
        $disco = Storage::disk();
        $presi = 0;
        $saltati = 0;

        foreach (self::DOCUMENTI as $chiave => $indirizzo) {
            $attuale = SiteSetting::get($chiave);

            if (! $this->option('forza') && is_string($attuale) && $attuale !== '' && $disco->exists($attuale)) {
                $this->line("· {$chiave}: gia' presente");
                $saltati++;

                continue;
            }

            $percorso = $this->scarica($indirizzo);

            if ($percorso === null) {
                $saltati++;

                continue;
            }

            // Il gruppo si lascia com'e': la colonna vale 'general' su queste
            // righe e `getAllGrouped()` ricava comunque 'legal' dal prefisso
            // della chiave. Riscriverlo qui cambierebbe righe che nessuno ha
            // chiesto di cambiare.
            SiteSetting::set($chiave, $percorso);

            $this->info("✓ {$chiave}: {$percorso}");
            $presi++;
        }

        $this->newLine();
        $this->line("Documenti importati: {$presi}, saltati: {$saltati}.");

        return self::SUCCESS;
    }

    /**
     * Scarica un PDF sul disco configurato e ne restituisce il percorso.
     *
     * Il nome del file si conserva, come fa il campo di upload del pannello
     * (`preserveFilenames`): sostituire il documento dal pannello riscrive lo
     * stesso percorso invece di lasciarne due copie.
     */
    private function scarica(string $indirizzo): ?string
    {
        try {
            $risposta = Http::timeout(30)->get($indirizzo);
        } catch (\Throwable $e) {
            $this->error("✗ {$indirizzo}: {$e->getMessage()}");

            return null;
        }

        if (! $risposta->successful()) {
            $this->error("✗ {$indirizzo}: HTTP {$risposta->status()}");

            return null;
        }

        $contenuto = $risposta->body();

        // Un PDF comincia sempre con "%PDF-". Senza questo controllo una
        // pagina di errore del sito di origine — servita con 200 — finirebbe
        // in archivio come se fosse il documento.
        if (! str_starts_with($contenuto, '%PDF-')) {
            $this->error("✗ {$indirizzo}: la risposta non e' un PDF");

            return null;
        }

        $nome = basename((string) parse_url($indirizzo, PHP_URL_PATH));
        $percorso = self::CARTELLA.'/'.$nome;

        Storage::disk()->put($percorso, $contenuto);

        return $percorso;
    }
}
