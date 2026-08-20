<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * La pagina CMS "Contatti" esponeva email, telefono, sede e dati fiscali dentro
 * `content_data`, ma footer, pagina Contatti, Comunicazione e Settore Giovanile
 * leggono quelle informazioni dalle impostazioni di sito (gruppo `contact`):
 * modificarle in redazione non cambiava niente online.
 *
 * I campi sono stati spostati in Impostazioni → Contatti. Qui i valori scritti
 * nella pagina vengono travasati nelle impostazioni — solo dove l'impostazione
 * è vuota, per non sovrascrivere quello che il sito sta già pubblicando — e le
 * copie doppie vengono tolte da `content_data`.
 */
return new class extends Migration
{
    /**
     * Chiavi duplicate: stesso nome nella pagina e nel gruppo `contact`.
     */
    private const RECAPITI = [
        'email', 'pec', 'phone', 'office_hours', 'address', 'city',
        'press_email', 'social_email', 'media_email', 'youth_email',
        'legal_piva', 'legal_cf', 'legal_fipav', 'legal_sdi',
    ];

    /**
     * Chiavi che non legge più nessuno: restavano da una versione precedente
     * della pagina, che mostrava una mappa con indirizzo scritto a mano.
     */
    private const RESIDUI = ['map_title', 'map_address'];

    public function up(): void
    {
        $row = DB::table('pages')->select('id', 'content_data')->where('slug', 'contatti')->first();

        if (! $row) {
            return;
        }

        $data = json_decode((string) $row->content_data, true);

        if (! is_array($data) || $data === []) {
            return;
        }

        $locales = config('app.supported_locales', ['it', 'en']);
        $isPerLocale = $data === array_intersect_key($data, array_flip($locales));
        $italiano = $isPerLocale ? ($data['it'] ?? reset($data)) : $data;

        foreach (self::RECAPITI as $chiave) {
            $valore = is_array($italiano) ? ($italiano[$chiave] ?? null) : null;

            if (! is_string($valore) || trim($valore) === '') {
                continue;
            }

            $impostazione = DB::table('site_settings')->where('key', $chiave)->first();

            if ($impostazione && filled($impostazione->value)) {
                // L'impostazione vince, perché è quella che il sito sta già
                // pubblicando. Se il valore scritto nella pagina era diverso
                // resta traccia nel log: è un dato inserito in redazione e
                // sparirebbe senza che nessuno se ne accorga.
                if (trim((string) $impostazione->value) !== trim($valore)) {
                    Log::warning('Recapito diverso fra la pagina Contatti e le impostazioni: viene tenuto quello delle impostazioni.', [
                        'chiave' => $chiave,
                        'impostazioni' => $impostazione->value,
                        'pagina_contatti' => $valore,
                    ]);
                }

                continue;
            }

            DB::table('site_settings')->updateOrInsert(
                ['key' => $chiave],
                [
                    'value' => $valore,
                    'type' => str_contains($chiave, 'email') ? 'email' : 'text',
                    'group' => 'contact',
                    'updated_at' => now(),
                    'created_at' => $impostazione->created_at ?? now(),
                ]
            );
        }

        $daRimuovere = array_merge(self::RECAPITI, self::RESIDUI);
        $pulito = [];

        foreach ($isPerLocale ? $data : ['__unico' => $data] as $locale => $contenuti) {
            if (is_array($contenuti)) {
                $contenuti = array_diff_key($contenuti, array_flip($daRimuovere));
            }

            $pulito[$locale] = $contenuti;
        }

        DB::table('pages')->where('id', $row->id)->update([
            'content_data' => json_encode(
                $isPerLocale ? $pulito : $pulito['__unico'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ]);

        SiteSetting::clearCache();
    }

    public function down(): void
    {
        // no-op: i recapiti restano dove il sito li legge davvero.
    }
};
