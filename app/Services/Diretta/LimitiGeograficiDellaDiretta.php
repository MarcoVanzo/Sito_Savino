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
 *
 * La home non aspetta mai YouTube: riceve l'ultimo esito salvato, e quando è
 * vecchio (o manca) la nuova domanda parte dopo aver risposto al visitatore
 * (`defer`). Prima chiedeva durante la richiesta: con Google lento la home
 * restava ferma fino al timeout del token.
 */
class LimitiGeograficiDellaDiretta
{
    /** La Lega può cambiare i limiti anche a diretta cominciata. */
    private const MINUTI_PRIMA_DI_RICHIEDERE = 10;

    /** Una risposta mancata si riprova presto, ma non a ogni visita della home. */
    private const MINUTI_PRIMA_DI_RIPROVARE = 2;

    /** Quanto resta buono l'ultimo esito mentre se ne chiede uno nuovo: una gara. */
    private const ORE_IN_MEMORIA = 3;

    /** @return bool|null true = limitata, false = libera, null = non si sa */
    public function perIlLink(?string $url): ?bool
    {
        $videoId = LiveStream::youtubeId($url);

        if ($videoId === null) {
            return null;
        }

        $salvato = Cache::get(self::chiave($videoId));

        if (! is_array($salvato) || self::daRichiedere($salvato)) {
            $this->richiediDopoLaRisposta($videoId);
        }

        return is_array($salvato) ? $salvato['limitata'] : null;
    }

    /** Chiede a YouTube adesso e salva l'esito: la usa `defer`, e i test. */
    public function verifica(string $videoId): ?bool
    {
        $limitata = $this->chiediAYoutube($videoId);

        Cache::put(
            self::chiave($videoId),
            ['limitata' => $limitata, 'verificato_il' => now()->getTimestamp()],
            now()->addHours(self::ORE_IN_MEMORIA),
        );

        return $limitata;
    }

    /**
     * Una domanda alla volta per video: con la home visitata da molti, senza
     * il segnaposto ogni richiesta ne accoderebbe una.
     */
    private function richiediDopoLaRisposta(string $videoId): void
    {
        if (! Cache::add(self::chiave($videoId).':in-corso', true, now()->addMinutes(1))) {
            return;
        }

        defer(fn () => $this->verifica($videoId));
    }

    /**
     * Le voci scritte dalla prima versione non hanno `verificato_il`: valgono
     * come vecchie e si richiedono.
     *
     * @param  array{limitata: bool|null, verificato_il?: int}  $salvato
     */
    private static function daRichiedere(array $salvato): bool
    {
        $minuti = $salvato['limitata'] === null ? self::MINUTI_PRIMA_DI_RIPROVARE : self::MINUTI_PRIMA_DI_RICHIEDERE;

        return now()->getTimestamp() - (int) ($salvato['verificato_il'] ?? 0) >= $minuti * 60;
    }

    private static function chiave(string $videoId): string
    {
        return 'diretta:limiti-geografici:'.$videoId;
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
