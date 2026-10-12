<?php

namespace App\Support\Diretta;

use App\Models\Game;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Services\Diretta\StatoPubblicoDellaDiretta;

/**
 * Gli sponsor della diretta di una gara come li riceve il sito.
 *
 * `null` = la diretta resta quella di sempre: interruttore generale spento,
 * nessuna diretta con sponsor per la gara, o niente da mostrare. Il sito
 * non deve mai bloccare la diretta per colpa degli sponsor.
 *
 * Il pre-roll arriva con le props della pagina; lo stato durante la gara
 * (cornice, spot della regia) lo legge il browser a parte.
 */
class DirettaPerIlSito
{
    /** Le relazioni da caricare prima di chiamare `perLaGara()` su molte gare. */
    public const RELAZIONI = [
        'liveSession.creativitaDelPreroll.media',
        'liveSession.creativitaDelPreroll.sponsor',
        'liveSession.creativitaDellaGara',
    ];

    /**
     * `stato` è l'indirizzo del JSON con cornici e regia (StatoPubblicoDellaDiretta),
     * `ora` quello dell'orologio del server: ci sono solo se la gara ha
     * creatività per la cornice.
     *
     * @return array{gara: int, preroll: ?array{salta_dopo: int, creativita: list<array<string, mixed>>}, stato: ?string, ora: ?string}|null
     */
    public static function perLaGara(Game $game): ?array
    {
        if (! ImpostazioniDellaDiretta::abilitata()) {
            return null;
        }

        $sessione = $game->liveSession;

        if (! $sessione instanceof LiveSession) {
            return null;
        }

        $preroll = self::preroll($sessione);
        $conCornici = $sessione->creativitaDellaGara->isNotEmpty();

        if ($preroll === null && ! $conCornici) {
            return null;
        }

        return [
            'gara' => $game->id,
            'preroll' => $preroll,
            'stato' => $conCornici ? app(StatoPubblicoDellaDiretta::class)->url($game->id) : null,
            'ora' => $conCornici ? route('api.diretta.ora') : null,
        ];
    }

    /**
     * Le creatività del pre-roll attive e non ancora scadute: quelle che
     * cominciano domani passano, le scarta il browser fino a quel giorno.
     *
     * @return array{salta_dopo: int, creativita: list<array<string, mixed>>}|null
     */
    private static function preroll(LiveSession $sessione): ?array
    {
        if (! $sessione->preroll_attivo) {
            return null;
        }

        $oggi = now()->toDateString();
        $creativita = $sessione->creativitaDelPreroll
            ->filter(fn (LiveCreative $c): bool => $c->attiva
                && ($c->valida_al === null || $c->valida_al->toDateString() >= $oggi)
                && $c->getFirstMedia(LiveCreative::COLLEZIONE_CONTENUTO) !== null)
            ->map(fn (LiveCreative $c): array => $c->perIlSito())
            ->values()
            ->all();

        if ($creativita === []) {
            return null;
        }

        return [
            'salta_dopo' => $sessione->preroll_salta_dopo_secondi,
            'creativita' => $creativita,
        ];
    }
}
