<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il materiale stampa stava in quattro caselle fisse — Foto, Loghi, Cartella
 * Stampa, Guida Media — decise una volta e non più cambiabili. Alla redazione
 * ne servono altre: il brand book e una cartella per ogni gara del calendario,
 * che sono ventisei.
 *
 * Le quattro voci esistenti diventano le prime dell'elenco `press_kits`.
 * Si conservano solo quelle con un file: le altre erano riquadri che online
 * sembravano scaricabili e non lo erano.
 */
return new class extends Migration
{
    private const CAMPI = ['icon', 'title', 'description', 'format', 'file'];

    public function up(): void
    {
        DB::table('pages')
            ->where('template', 'Public/Comunicazione')
            ->orderBy('id')
            ->each(function (object $page) {
                $contenuti = json_decode((string) $page->content_data, true);

                if (! is_array($contenuti)) {
                    return;
                }

                $contenuti = $this->convertiPerLingua($contenuti);

                DB::table('pages')
                    ->where('id', $page->id)
                    ->update(['content_data' => json_encode($contenuti, JSON_UNESCAPED_UNICODE)]);
            });
    }

    /**
     * `content_data` è tradotto: al primo livello ci sono le lingue, e dentro
     * ognuna la struttura piatta dei campi.
     *
     * @param  array<string, mixed>  $contenuti
     * @return array<string, mixed>
     */
    private function convertiPerLingua(array $contenuti): array
    {
        foreach ($contenuti as $lingua => $campi) {
            if (is_array($campi)) {
                $contenuti[$lingua] = $this->converti($campi);
            }
        }

        // Righe storiche senza il livello della lingua.
        if (isset($contenuti['press_kit_1_file']) || isset($contenuti['press_kit_1_title'])) {
            $contenuti = $this->converti($contenuti);
        }

        return $contenuti;
    }

    /**
     * @param  array<string, mixed>  $campi
     * @return array<string, mixed>
     */
    private function converti(array $campi): array
    {
        $elenco = [];

        for ($n = 1; $n <= 4; $n++) {
            $voce = [];

            foreach (self::CAMPI as $campo) {
                $chiave = "press_kit_{$n}_{$campo}";

                if (isset($campi[$chiave]) && $campi[$chiave] !== '') {
                    $voce[$campo] = $campi[$chiave];
                }

                unset($campi[$chiave]);
            }

            // Senza file non c'è niente da scaricare, e la voce non serve.
            if (! empty($voce['file'])) {
                $elenco[] = $voce;
            }
        }

        // Un elenco già presente ha la precedenza: la migrazione non deve
        // sovrascrivere il lavoro fatto dopo il primo passaggio.
        if ($elenco !== [] && empty($campi['press_kits'])) {
            $campi['press_kits'] = $elenco;
        }

        return $campi;
    }

    /**
     * Non reversibile: le quattro caselle avevano una semantica fissa che
     * l'elenco libero non conserva, e riassegnarle a indovinare sarebbe peggio
     * che lasciare i dati dove sono.
     */
    public function down(): void
    {
        // no-op documentato
    }
};
