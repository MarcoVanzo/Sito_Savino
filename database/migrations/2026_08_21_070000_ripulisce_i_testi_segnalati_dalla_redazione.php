<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tre testi che la redazione non riusciva a togliere o correggere.
 *
 * - Sul Settore Giovanile restava "Il settore giovanile rappresenta il cuore
 *   pulsante…": non stava nei campi del modello ma nell'editor grande, e in
 *   redazione non lo trovavano.
 * - L'etichetta della Campagna Abbonamenti diceva ancora "Vivi l'Emozione"
 *   nonostante il pannello mostrasse "Believe": il salvataggio della pagina
 *   cancellava il campo (difetto corretto a parte), quindi il valore era
 *   rimasto quello vecchio in archivio.
 * - "Le Nostre Squadre" e "I Nostri Valori" sul Settore Giovanile: la
 *   redazione ha chiesto di toglierle e non sapeva che si svuotano da sole.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->togliIlTestoDelVivaio();
        $this->correggeLEtichettaDegliAbbonamenti();
    }

    public function down(): void
    {
        // Testi tolti su richiesta: rimetterli non è un rollback utile.
    }

    /**
     * Il paragrafo sta nel contenuto testuale della pagina, non nei campi.
     */
    private function togliIlTestoDelVivaio(): void
    {
        foreach (['settore-giovanile', 'youth'] as $slug) {
            $pagina = DB::table('pages')->where('slug', $slug)->first();

            if (! $pagina) {
                continue;
            }

            $contenuto = json_decode((string) $pagina->content, true);

            if (! is_array($contenuto)) {
                continue;
            }

            foreach ($contenuto as $lingua => $testo) {
                if (! is_string($testo)) {
                    continue;
                }

                // Via il paragrafo che comincia con quella frase, in qualunque
                // lingua sia stato scritto.
                $ripulito = preg_replace(
                    '#<p>\s*(Il settore giovanile rappresenta il cuore pulsante|The youth sector is the beating heart).*?</p>#isu',
                    '',
                    $testo,
                );

                $contenuto[$lingua] = trim((string) $ripulito);
            }

            DB::table('pages')
                ->where('id', $pagina->id)
                ->update(['content' => json_encode($contenuto, JSON_UNESCAPED_UNICODE)]);
        }
    }

    /**
     * Le pagine di biglietteria portano il claim della stagione.
     */
    private function correggeLEtichettaDegliAbbonamenti(): void
    {
        DB::table('pages')
            ->where('template', 'Public/Ticketing')
            ->orderBy('id')
            ->each(function (object $pagina) {
                $contenuti = json_decode((string) $pagina->content_data, true);

                if (! is_array($contenuti)) {
                    return;
                }

                foreach ($contenuti as $lingua => $campi) {
                    if (is_array($campi) && isset($campi['hero_label'])) {
                        $contenuti[$lingua]['hero_label'] = 'Believe';
                    }
                }

                DB::table('pages')
                    ->where('id', $pagina->id)
                    ->update(['content_data' => json_encode($contenuti, JSON_UNESCAPED_UNICODE)]);
            });
    }
};
