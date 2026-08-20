<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tre pagine esistevano ma erano vuote, e una pagina vuota non è neutra:
 *
 * - Talent Day condivideva il modello del Summer Camp e, senza contenuti
 *   propri, mostrava quelli. Chi cercava le prove di selezione leggeva gli
 *   orari del camp estivo. Ora ha un modello suo (`Public/TalentDay`).
 * - Campagna Abbonamenti e Biglietteria non avevano listino, e la redazione
 *   non trovava dove inserirlo perché i campi delle tariffe ridotte non
 *   esistevano ancora.
 * - Progetto Affiliazioni era una pagina bianca.
 *
 * I testi arrivano dal sito precedente e stanno in
 * `database/data/revisione_agosto_contenuti.php`. Da qui in avanti si
 * modificano dal pannello: questa migrazione non si rilancia.
 *
 * Non tocca le pagine che hanno già un contenuto: se la redazione ha scritto
 * qualcosa prima del deploy, resta il suo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $contenuti = require database_path('data/revisione_agosto_contenuti.php');

        $this->preparaTalentDay($contenuti['talent-day']);
        $this->preparaAbbonamenti($contenuti['abbonamenti']);
        $this->preparaAffiliazioni($contenuti['affiliazioni']);
    }

    public function down(): void
    {
        // Talent Day torna al modello precedente; i testi restano, sono corretti.
        DB::table('pages')
            ->where('slug', 'talent-day')
            ->where('template', 'Public/TalentDay')
            ->update(['template' => 'Public/SummerCamp']);
    }

    /**
     * @param  array<string, array<string, mixed>>  $contenuti
     */
    private function preparaTalentDay(array $contenuti): void
    {
        $pagina = DB::table('pages')->where('slug', 'talent-day')->first();

        if (! $pagina) {
            return;
        }

        // Qui i nuovi valori vincono su quelli presenti, al contrario delle
        // altre due pagine: il contenuto che c'era era quello del Summer Camp,
        // ereditato dal modello condiviso. Tenerlo vorrebbe dire lasciare in
        // pagina "Un'estate indimenticabile" sopra le date delle selezioni.
        $unificati = $this->decodifica($pagina->content_data);

        foreach ($contenuti as $lingua => $campi) {
            $esistenti = is_array($unificati[$lingua] ?? null) ? $unificati[$lingua] : [];
            $unificati[$lingua] = array_merge($esistenti, $campi);
        }

        DB::table('pages')->where('id', $pagina->id)->update([
            'template' => 'Public/TalentDay',
            'content_data' => json_encode($unificati, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Il listino vale sia per "Campagna Abbonamenti" sia per "Biglietteria":
     * sono due porte sulla stessa informazione.
     *
     * @param  array<string, array<string, mixed>>  $contenuti
     */
    private function preparaAbbonamenti(array $contenuti): void
    {
        foreach (['abbonamenti', 'biglietteria'] as $slug) {
            $pagina = DB::table('pages')->where('slug', $slug)->first();

            if (! $pagina) {
                continue;
            }

            $esistenti = $this->decodifica($pagina->content_data);

            // Un listino già pubblicato non si sovrascrive.
            if (! empty($esistenti['it']['plans']) || ! empty($esistenti['plans'])) {
                continue;
            }

            DB::table('pages')->where('id', $pagina->id)->update([
                'content_data' => json_encode(
                    $this->uniti($pagina->content_data, $contenuti),
                    JSON_UNESCAPED_UNICODE
                ),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $contenuti
     */
    private function preparaAffiliazioni(array $contenuti): void
    {
        $pagina = DB::table('pages')->where('slug', 'affiliazioni')->first();

        if (! $pagina) {
            return;
        }

        $attuale = $this->decodifica($pagina->content);

        // Solo se la pagina è davvero vuota.
        if (trim(strip_tags((string) ($attuale['it'] ?? $pagina->content ?? ''))) !== '') {
            return;
        }

        DB::table('pages')->where('id', $pagina->id)->update([
            'content' => json_encode($contenuti, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Unisce i nuovi campi a quelli già presenti, lingua per lingua, senza
     * sovrascrivere quello che la redazione ha già scritto.
     *
     * @param  array<string, array<string, mixed>>  $nuovi
     * @return array<string, array<string, mixed>>
     */
    private function uniti(mixed $attuali, array $nuovi): array
    {
        $attuali = $this->decodifica($attuali);
        $risultato = $attuali;

        foreach ($nuovi as $lingua => $campi) {
            $esistenti = is_array($attuali[$lingua] ?? null) ? $attuali[$lingua] : [];

            // I campi già valorizzati vincono su quelli inseriti qui.
            $risultato[$lingua] = array_merge($campi, array_filter(
                $esistenti,
                fn ($valore) => $valore !== null && $valore !== '' && $valore !== [],
            ));
        }

        return $risultato;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodifica(mixed $valore): array
    {
        if (is_array($valore)) {
            return $valore;
        }

        $decodificato = json_decode((string) $valore, true);

        return is_array($decodificato) ? $decodificato : [];
    }
};
