<?php

namespace App\Services\Diretta;

use App\Services\Youtube\YoutubeClient;
use App\Services\Youtube\YoutubeException;
use App\Support\LiveStream;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Se la diretta di una gara si vede ovunque o solo in alcuni paesi.
 *
 * La Lega blocca le sue dirette in certi paesi, e il link che la redazione
 * copia non lo dice: lo sa solo YouTube. Aprire da sola una diretta che al
 * visitatore risponde «video non disponibile nel tuo paese» sarebbe peggio
 * che non aprirla, quindi chi chiede ottiene `false` solo quando YouTube ha
 * confermato che limiti non ce ne sono. Altre piattaforme, niente service
 * account o YouTube che non risponde: `null`, cioè «non si sa».
 */
class LimitiGeograficiDellaDiretta
{
    /** La Lega può cambiare i limiti anche a diretta cominciata. */
    private const MINUTI_IN_CACHE = 10;

    /** Una risposta mancata si riprova presto, ma non a ogni visita della home. */
    private const MINUTI_IN_CACHE_SE_NON_SI_SA = 2;

    /** @return bool|null true = limitata, false = libera, null = non si sa */
    public function perIlLink(?string $url): ?bool
    {
        $videoId = LiveStream::youtubeId($url);

        if ($videoId === null) {
            return null;
        }

        $chiave = 'diretta:limiti-geografici:'.$videoId;
        $salvato = Cache::get($chiave);

        if (is_array($salvato)) {
            return $salvato['limitata'];
        }

        $limitata = $this->chiediAYoutube($videoId);

        Cache::put(
            $chiave,
            ['limitata' => $limitata],
            now()->addMinutes($limitata === null ? self::MINUTI_IN_CACHE_SE_NON_SI_SA : self::MINUTI_IN_CACHE),
        );

        return $limitata;
    }

    private function chiediAYoutube(string $videoId): ?bool
    {
        $client = YoutubeClient::fromConfig();

        if ($client === null) {
            return null;
        }

        try {
            return $client->haLimitiPerPaese($videoId);
        } catch (YoutubeException $e) {
            Log::warning('Diretta: limiti per paese non verificati', ['video' => $videoId, 'errore' => $e->getMessage()]);

            return null;
        }
    }
}
