<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Due correzioni di contenuto chieste dalla redazione.
 *
 * 1. L'intestazione "Sede Amministrativa" della pagina Contatti era una
 *    traduzione fissa: il pannello rimandava a Impostazioni → Contatti, dove
 *    il campo non c'era. Diventa l'impostazione `address_label` del gruppo
 *    `contact`, creata qui perché `SiteSetting::set()` metterebbe una chiave
 *    nuova nel gruppo predefinito, che non arriva al frontend.
 *
 * 2. La mappa del Palazzetto del seeder aveva coordinate inventate (zona
 *    Peretola, non Via del Cavallaccio). Si svuota solo se è ancora quella:
 *    vuota, la pagina centra la mappa sull'indirizzo della struttura. Una
 *    mappa scelta dalla redazione non si tocca.
 *
 * 3. La CTA "Prossima partita" della homepage deve aprire il calendario
 *    filtrato sul Savino (`?squadra=savino`), non l'intero campionato. Si
 *    cambia solo se punta ancora ai risultati senza filtro.
 */
return new class extends Migration
{
    private const MAPPA_SEGNAPOSTO = '!4v1700000000000!';

    public function up(): void
    {
        if (! DB::table('site_settings')->where('key', 'address_label')->exists()) {
            DB::table('site_settings')->insert([
                'key' => 'address_label',
                'value' => json_encode(['it' => 'Sede Amministrativa', 'en' => 'Administrative Office']),
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Etichetta della sede',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            SiteSetting::clearCache();
        }

        foreach (DB::table('pages')->where('slug', 'palazzetto')->get(['id', 'content_data']) as $pagina) {
            $contenuti = json_decode((string) $pagina->content_data, true);

            if (! is_array($contenuti)) {
                continue;
            }

            $cambiata = false;

            foreach ($contenuti as $lingua => $valori) {
                if (is_array($valori) && str_contains((string) ($valori['maps_iframe_src'] ?? ''), self::MAPPA_SEGNAPOSTO)) {
                    $contenuti[$lingua]['maps_iframe_src'] = '';
                    $cambiata = true;
                }
            }

            if ($cambiata) {
                DB::table('pages')->where('id', $pagina->id)->update([
                    'content_data' => json_encode($contenuti, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        $ctaAggiornata = DB::table('site_settings')
            ->where('key', 'hero_cta1_url')
            ->whereIn('value', ['/risultati', '/risultati/', '/stagione/risultati', '/stagione/risultati/'])
            ->update(['value' => '/stagione/risultati?squadra=savino', 'updated_at' => now()]);

        if ($ctaAggiornata > 0) {
            SiteSetting::clearCache();
        }
    }

    /**
     * La mappa segnaposto non si ripristina: era sbagliata.
     */
    public function down(): void
    {
        DB::table('site_settings')->where('key', 'address_label')->delete();
        SiteSetting::clearCache();
    }
};
