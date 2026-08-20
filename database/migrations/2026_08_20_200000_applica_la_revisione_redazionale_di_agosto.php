<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Correzioni di contenuto chieste dalla redazione nella revisione di agosto.
 *
 * Sono tutte cose che la redazione potrebbe fare a mano dal pannello, ma sono
 * anche cose che oggi online sono sbagliate: metterle qui le corregge al primo
 * deploy invece di lasciarle in una lista di cose da fare.
 *
 * Il down() rimette i valori precedenti solo dove erano univoci. Dove il
 * valore precedente era sbagliato (il nome del brand abbreviato, i quarant'anni
 * di storia) non si torna indietro: ripristinarli non e' un rollback utile.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->correggeINumeriDelClub();
        $this->correggeIlNamingDelBrand();
        $this->togliIlVecchioClaimDalleSlide();
    }

    public function down(): void
    {
        // I numeri e il naming non si ripristinano: vedi il commento in testa.
    }

    /**
     * "40+ Anni di Storia" contava la pallavolo a Scandicci, non la Savino Del
     * Bene Volley, che esiste dal 2013. E la versione inglese era rimasta a
     * "4,000+ Seats at Palazzo Wanny": impianto rinominato e capienza sbagliata.
     */
    private function correggeINumeriDelClub(): void
    {
        $numeri = [
            'it' => [
                ['value' => '13', 'label' => 'Anni di Savino Del Bene Volley', 'icon' => '🏆'],
                ['value' => '3.500+', 'label' => 'Posti al Pala BigMat', 'icon' => '🏟️'],
                ['value' => 'Serie A1', 'label' => 'Lega Volley Femminile', 'icon' => '🏐'],
                ['value' => 'CEV', 'label' => 'Champions League', 'icon' => '🌍'],
            ],
            'en' => [
                ['value' => '13', 'label' => 'Years of Savino Del Bene Volley', 'icon' => '🏆'],
                ['value' => '3,500+', 'label' => 'Seats at Pala BigMat', 'icon' => '🏟️'],
                ['value' => 'Serie A1', 'label' => 'Lega Volley Femminile', 'icon' => '🏐'],
                ['value' => 'CEV', 'label' => 'Champions League', 'icon' => '🌍'],
            ],
        ];

        DB::table('site_settings')
            ->where('group', 'home')
            ->where('key', 'stats')
            ->update(['value' => json_encode($numeri, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * La Brand & Digital Style Guide vieta le abbreviazioni: la denominazione
     * e' "Savino Del Bene Volley", con la D maiuscola. "SDB" era nel menu
     * principale e nella categoria delle news del vivaio.
     */
    private function correggeIlNamingDelBrand(): void
    {
        // Le voci si riconoscono dalla destinazione, non dall'identificativo:
        // quello lo assegna l'auto-increment e basta un inserimento in piu' in
        // un ambiente perche' le righe si spostino di una posizione. Cablarlo
        // qui significherebbe rinominare la voce sbagliata senza accorgersene.
        $etichette = [
            '/sponsor/title-sponsor/' => ['it' => 'Title Sponsor', 'en' => 'Title Sponsor'],
            '/youth/' => ['it' => 'Savino Del Bene Youth', 'en' => 'Savino Del Bene Youth'],
        ];

        foreach ($etichette as $url => $etichetta) {
            DB::table('menu_items')
                ->whereIn('url', [$url, rtrim($url, '/')])
                ->update(['label' => json_encode($etichetta, JSON_UNESCAPED_UNICODE)]);
        }

        // La categoria delle news si riconosce dallo slug, non dall'id: quello
        // cambia fra ambienti.
        DB::table('categories')
            ->where('slug', 'sdb-youth')
            ->update(['name' => json_encode(
                ['it' => 'Savino Del Bene Youth', 'en' => 'Savino Del Bene Youth'],
                JSON_UNESCAPED_UNICODE
            )]);

        // "Sponsor News" era rimasto solo in inglese.
        DB::table('categories')
            ->where('slug', 'sponsor')
            ->update(['name' => json_encode(
                ['it' => 'Sponsor', 'en' => 'Sponsor'],
                JSON_UNESCAPED_UNICODE
            )]);
    }

    /**
     * Il claim della homepage e' "Believe" da luglio, ma "Scatena la Potenza"
     * era rimasto nei sottotitoli delle slide. Oggi la home usa delle slide
     * solo le immagini, quindi non si vedeva: resta comunque un testo vecchio
     * che la redazione trova nel pannello e crede pubblicato.
     */
    private function togliIlVecchioClaimDalleSlide(): void
    {
        $sostituzioni = [
            'Scatena la Potenza.' => 'Believe',
            'Unleash the Power.' => 'Believe',
            'SCATENALA' => 'BELIEVE',
            'UNLEASH IT' => 'BELIEVE',
            'TUTTA LA POTENZA' => 'BELIEVE',
            'ALL THE POWER' => 'BELIEVE',
        ];

        DB::table('hero_slides')
            ->orderBy('id')
            ->each(function (object $slide) use ($sostituzioni) {
                $aggiornamenti = [];

                foreach (['title', 'subtitle'] as $campo) {
                    $valore = (string) ($slide->{$campo} ?? '');
                    $nuovo = strtr($valore, $sostituzioni);

                    if ($nuovo !== $valore) {
                        $aggiornamenti[$campo] = $nuovo;
                    }
                }

                if ($aggiornamenti !== []) {
                    DB::table('hero_slides')->where('id', $slide->id)->update($aggiornamenti);
                }
            });

        DB::table('site_settings')
            ->where('group', 'home')
            ->where('key', 'hero_tagline')
            ->update(['value' => json_encode(['it' => 'Believe', 'en' => 'Believe'], JSON_UNESCAPED_UNICODE)]);
    }
};
