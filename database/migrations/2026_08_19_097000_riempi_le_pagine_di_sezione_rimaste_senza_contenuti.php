<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nove pagine di sezione (abbonamenti, biglietteria, accrediti stampa, cartelle
 * stampa, progetti sociali, volley 4 all, settore giovanile, talent day,
 * organigramma) avevano `content_data` vuoto: online mostravano i testi di
 * ripiego del template, in redazione i campi erano tutti vuoti.
 *
 * Qui ricevono come punto di partenza esattamente i testi che il sito sta già
 * pubblicando, così quello che si legge online è anche quello che si trova nel
 * pannello — e da lì si può differenziare pagina per pagina.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = require database_path('data/page_template_defaults.php');
        $locales = config('app.supported_locales', ['it', 'en']);

        $pagine = DB::table('pages')
            ->select('id', 'slug', 'template', 'content_data')
            ->whereIn('template', array_keys($defaults))
            ->get();

        foreach ($pagine as $pagina) {
            $data = json_decode((string) $pagina->content_data, true);
            $data = is_array($data) ? $data : [];
            $data = $this->perLingua($data, $locales);

            // Le chiavi già presenti non si toccano mai: si aggiungono solo
            // quelle che mancano, così una pagina già curata dalla redazione
            // resta com'è e guadagna soltanto i campi che erano invisibili.
            foreach ($locales as $locale) {
                $iniziali = $defaults[$pagina->template][$locale]
                    ?? $defaults[$pagina->template]['it']
                    ?? [];

                $esistenti = is_array($data[$locale] ?? null) ? $data[$locale] : [];

                $data[$locale] = $esistenti + $iniziali;
            }

            DB::table('pages')->where('id', $pagina->id)->update([
                'content_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    /**
     * `content_data` è translatable: al primo livello ci sono i codici lingua.
     * Una pagina rimasta in forma piatta viene ricondotta a quella struttura,
     * altrimenti i valori nuovi finirebbero accanto ai vecchi senza sostituirli.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $locales
     * @return array<string, mixed>
     */
    private function perLingua(array $data, array $locales): array
    {
        if ($data === []) {
            return $data;
        }

        $chiavi = array_keys($data);

        if (array_diff($chiavi, $locales) === []) {
            return $data;
        }

        $normalizzato = [];

        foreach ($locales as $locale) {
            $normalizzato[$locale] = $data;
        }

        return $normalizzato;
    }

    public function down(): void
    {
        // no-op: svuotare riporterebbe le pagine allo stato in cui i testi
        // online non erano modificabili da nessuna parte.
    }
};
