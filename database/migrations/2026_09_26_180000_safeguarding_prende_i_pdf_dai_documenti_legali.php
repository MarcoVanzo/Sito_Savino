<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * I quattro documenti della pagina Safeguarding erano una copia di quelli di
 * Documenti Legali: due in `safeguarding/`, due con il percorso di `legal/`
 * scritto dentro la pagina. Sostituendo un PDF in Documenti Legali il footer
 * si aggiornava, Safeguarding no.
 *
 * Ogni documento riconosciuto dal nome del file passa a rimandare per chiave
 * (`documento_legale`) e perde la copia del file. A guardie: si tocca solo un
 * documento che non ha già un rimando e il cui file è uno di quei quattro.
 */
return new class extends Migration
{
    /** Nome del file => chiave in Documenti Legali. */
    private const FILE = [
        'Modello-Organizzativo_compressed.pdf' => 'modello_organizzativo',
        'Protocollo-1-Codice-di-condotta.pdf' => 'codice_tutela_minori',
        'Protocollo-2-Bullismo-e-cyberbullismo.pdf' => 'protocollo_bullismo',
        'Protocollo-3-Razzismo-e-xenofobia.pdf' => 'protocollo_razzismo',
    ];

    public function up(): void
    {
        $pagina = DB::table('pages')->where('slug', 'safeguarding')->first(['id', 'content_data']);

        if ($pagina === null) {
            return;
        }

        $contenuti = json_decode((string) $pagina->content_data, true);

        if (! is_array($contenuti)) {
            return;
        }

        $cambiato = false;

        foreach ($contenuti as $lingua => $dati) {
            if (! is_array($dati['documents'] ?? null)) {
                continue;
            }

            foreach ($dati['documents'] as $i => $documento) {
                $chiave = $this->chiaveDelDocumento($documento);

                if ($chiave === null) {
                    continue;
                }

                $documento['documento_legale'] = $chiave;
                unset($documento['file']);
                $contenuti[$lingua]['documents'][$i] = $documento;
                $cambiato = true;
            }
        }

        if ($cambiato) {
            DB::table('pages')->where('id', $pagina->id)->update([
                'content_data' => json_encode($contenuti, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        // I file restano su Spaces e Documenti Legali punta agli stessi: il
        // rimando per chiave dà lo stesso link di prima, non c'è niente da
        // ripristinare.
    }

    private function chiaveDelDocumento(mixed $documento): ?string
    {
        if (! is_array($documento) || filled($documento['documento_legale'] ?? null)) {
            return null;
        }

        $file = $documento['file'] ?? null;

        if (! is_string($file) || ! str_starts_with($file, 'safeguarding/') && ! str_starts_with($file, 'legal/')) {
            return null;
        }

        return self::FILE[basename($file)] ?? null;
    }
};
