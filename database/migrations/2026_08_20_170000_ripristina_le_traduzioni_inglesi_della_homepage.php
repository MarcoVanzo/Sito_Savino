<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il 20 agosto un salvataggio di Impostazioni → Homepage ha riscritto come testo
 * semplice nove voci che in archivio erano JSON per lingua: la pagina caricava i
 * valori già risolti in italiano e li rimandava indietro così com'erano, quindi
 * l'inglese di claim, pulsanti, banner e titoli è sparito in un colpo solo senza
 * che nessuno toccasse quei campi.
 *
 * Il difetto è chiuso — le impostazioni tradotte hanno ora un campo per lingua e
 * il salvataggio le riunisce — ma i valori persi non tornano da soli. Qui si
 * rimettono le traduzioni note, tenendo l'italiano che c'è adesso: nel frattempo
 * il claim è diventato "BELIEVE", ed è una scelta di redazione, non un danno.
 *
 * Per il claim l'inglese vale quanto l'italiano: "BELIEVE" è già inglese.
 */
return new class extends Migration
{
    /**
     * Traduzione inglese per chiave. `null` significa "uguale all'italiano".
     */
    private const INGLESE = [
        'hero_tagline' => null,
        'hero_cta1_label' => 'Next Match',
        'hero_cta2_label' => 'Ticketing',
        'cta_ticketing_title' => 'Ticketing',
        'cta_ticketing_text' => 'Buy tickets for the next match',
        'cta_shop_title' => 'Official Shop',
        'cta_shop_text' => 'Jerseys, merchandise and team accessories',
        'stats_title' => 'The Club in Numbers',
        'stats_subtitle' => 'The Numbers',
    ];

    public function up(): void
    {
        foreach (self::INGLESE as $chiave => $inglese) {
            $valore = DB::table('site_settings')->where('key', $chiave)->value('value');

            if (! is_string($valore) || $valore === '' || $this->giaTradotto($valore)) {
                continue;
            }

            DB::table('site_settings')->where('key', $chiave)->update([
                'value' => json_encode(
                    ['it' => $valore, 'en' => $inglese ?? $valore],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                'updated_at' => now(),
            ]);
        }

        SiteSetting::clearCache();
    }

    public function down(): void
    {
        // no-op: tornare al testo semplice rifarebbe il danno.
    }

    /**
     * Una voce già per lingua non si tocca: potrebbe essere stata sistemata in
     * redazione dopo il rilascio, e sarebbe la versione buona.
     */
    private function giaTradotto(string $valore): bool
    {
        $decodificato = json_decode($valore, true);

        return is_array($decodificato) && array_intersect(
            config('app.supported_locales', ['it', 'en']),
            array_keys($decodificato)
        ) !== [];
    }
};
