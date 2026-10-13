<?php

namespace App\Support\Diretta;

use App\Enums\UsoCreativita;
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
    /** Le cache delle pagine che portano il pre-roll con la gara (home e calendario). */
    /** In prova la cornice torna ogni minuto. */
    public const OGNI_MINUTI_IN_PROVA = 1;

    public const CHIAVI_DI_CACHE = ['public:home', 'public:risultati'];

    /** Le relazioni da caricare prima di chiamare `perLaGara()` su molte gare. */
    public const RELAZIONI = [
        'liveSession.creativitaDelPreroll.media',
        'liveSession.creativitaDelPreroll.sponsor',
        'liveSession.creativitaDellaGara',
    ];

    /**
     * `stato` è l'indirizzo del JSON con cornici e regia (StatoPubblicoDellaDiretta),
     * `ora` quello dell'orologio del server: ci sono solo se la gara ha
     * creatività per la cornice. `eventi` riceve i contatori del report.
     *
     * @return array{gara: int, preroll: ?array{salta_dopo: int, creativita: list<array<string, mixed>>}, stato: ?string, ora: ?string, eventi: string}|null
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
            'eventi' => route('api.diretta.eventi', $game),
        ];
    }

    /**
     * La diretta della gara per la prova dello staff (`/prova-diretta`):
     * come la vedrebbe il tifoso, ma anche a interruttore spento, senza
     * contatori per il report e con la cornice che torna ogni minuto da
     * quando parte la diretta (`inizio` lo mette il browser), per vederla
     * senza aspettare il ciclo vero. La regia non la tocca.
     *
     * @return array<string, mixed>|null
     */
    public static function perLaProva(Game $game): ?array
    {
        $sessione = $game->liveSession;

        if (! $sessione instanceof LiveSession) {
            return null;
        }

        $sessione->loadMissing(['creativitaDelPreroll.media', 'creativitaDelPreroll.sponsor', 'creativitaDellaGara.media', 'creativitaDellaGara.sponsor']);
        $preroll = self::preroll($sessione);
        $cornici = $sessione->creativitaDellaGara
            ->filter(fn (LiveCreative $c): bool => $c->valida())
            ->map(fn (LiveCreative $c): array => $c->perIlSito())
            ->values()
            ->all();

        if ($preroll === null && $cornici === []) {
            return null;
        }

        return [
            'gara' => $game->id,
            'prova' => true,
            'preroll' => $preroll,
            'stato' => null,
            'ora' => null,
            'eventi' => null,
            'stato_prova' => $cornici === [] ? null : [
                'versione' => 1,
                'stato' => 'in_onda',
                'ciclo' => [
                    'attivo' => true,
                    'inizio' => null,
                    'fine' => null,
                    'ogni_minuti' => self::OGNI_MINUTI_IN_PROVA,
                    'durata_secondi' => min($sessione->ciclo_durata_secondi, 45),
                ],
                'contenuto' => null,
                'playlist' => $cornici,
            ],
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
            ->filter(fn (LiveCreative $c): bool => $c->uso === UsoCreativita::Preroll
                && $c->attiva
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
