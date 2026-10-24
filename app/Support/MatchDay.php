<?php

namespace App\Support;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\SiteSetting;

/**
 * La modalità Match Day della homepage: fascia «Oggi si gioca», sezione della
 * partita in tema scuro e pop-up verso la biglietteria.
 *
 * La accende l'impostazione `match_day_modalita` (Impostazioni → Homepage):
 * `auto` nel giorno di una gara della prima squadra (A1, mai il vivaio), fino a tre ore dal fischio
 * d'inizio; `off` la spegne; `on` la accende a mano **solo per il giorno in cui
 * la si sceglie** (`match_day_acceso_il`): dimenticata accesa, la home direbbe
 * «Oggi si gioca» per settimane.
 *
 * Il pop-up vende biglietti: in automatico compare solo per le gare in casa e
 * prima del fischio d'inizio. Acceso a mano, decide la redazione, ma mai su
 * una trasferta: i biglietti li vende la squadra di casa.
 *
 * Dove non ci sono biglietti da vendere (trasferta, o gara già cominciata) il
 * pop-up porta alla diretta, se la gara ne ha una incorporabile: da un'ora
 * prima del fischio d'inizio, come i contatori della diretta.
 */
class MatchDay
{
    public const AUTO = 'auto';

    public const ACCESO = 'on';

    public const SPENTO = 'off';

    /**
     * Quanto resta acceso dopo l'inizio: una gara dura al massimo un paio
     * d'ore e mezza. Stesso valore del conto alla rovescia
     * (`conteggioAllaPartita.js`), che per lo stesso tempo dice «in corso».
     */
    public const ORE_DOPO_L_INIZIO = 3;

    /** Da quanto prima del fischio d'inizio il pop-up offre la diretta. */
    public const MINUTI_PRIMA_DELLA_DIRETTA = 60;

    /**
     * @return array{attivo: bool, gara: ?Game, in_casa: bool, popup: ?array{titolo: string, testo: string, pulsante: string, url: string}, popup_diretta: bool, diretta_vicina: bool}
     */
    public static function stato(): array
    {
        $modalita = self::modalita();

        if ($modalita === self::SPENTO) {
            return self::spento();
        }

        $gara = self::garaDiOggi();

        if ($gara === null && $modalita === self::AUTO) {
            return self::spento();
        }

        $inCasa = (bool) $gara?->homeTeam?->is_internal;
        $prima = $gara !== null && $gara->match_date->isFuture();
        // Acceso a mano senza una gara oggi, la home mostra la prossima: se è
        // una trasferta, il pop-up venderebbe biglietti che non sono nostri.
        $riferimento = $gara ?? self::prossimaGara();
        $trasferta = $riferimento !== null && ! $riferimento->homeTeam?->is_internal;

        $popup = self::popupAttivo() && ! $trasferta && ($modalita === self::ACCESO || ($inCasa && $prima))
            ? self::popup()
            : null;

        $direttaVicina = self::direttaVicina($gara);

        return [
            'attivo' => true,
            'gara' => $gara,
            'in_casa' => $inCasa,
            'popup' => $popup,
            // Il pop-up apre la finestra del sito: un link che porta fuori no.
            'popup_diretta' => $popup === null && self::popupAttivo() && $direttaVicina
                && LiveStream::embedUrl($gara?->stream_url) !== null,
            'diretta_vicina' => $direttaVicina,
        ];
    }

    public static function modalita(): string
    {
        $valore = (string) SiteSetting::get('match_day_modalita', self::AUTO);

        if ($valore === self::ACCESO && SiteSetting::get('match_day_acceso_il') !== now()->toDateString()) {
            return self::AUTO;
        }

        return in_array($valore, [self::AUTO, self::ACCESO, self::SPENTO], true) ? $valore : self::AUTO;
    }

    /**
     * La gara della prima squadra in programma oggi, anche se è già cominciata:
     * durante la partita la home deve restare in Match Day. Finita (tre ore
     * dopo l'inizio) no, e nemmeno le rinviate.
     *
     * La finestra parte da tre ore fa e non da mezzanotte: una gara alle 21:30
     * resta in pagina fino alle 00:30, invece di sparire allo scoccare del
     * giorno dopo con la partita ancora in corso.
     */
    public static function garaDiOggi(): ?Game
    {
        return Game::with(['homeTeam', 'awayTeam'])
            ->where('match_date', '>', now()->subHours(self::ORE_DOPO_L_INIZIO))
            ->where('match_date', '<=', now()->endOfDay())
            ->where('status', '!=', GameStatus::Postponed)
            ->dellaPrimaSquadra()
            ->orderBy('match_date')
            ->first();
    }

    /**
     * La prossima gara in programma della prima squadra, la stessa che la home
     * mostra nella sezione della partita quando non si gioca oggi.
     */
    public static function prossimaGara(): ?Game
    {
        return Game::with(['homeTeam', 'awayTeam'])
            ->where('status', GameStatus::Scheduled)
            ->where('match_date', '>=', now())
            ->dellaPrimaSquadra()
            ->orderBy('match_date')
            ->first();
    }

    /**
     * La gara ha un link di diretta e manca al massimo un'ora al fischio
     * d'inizio (o è già cominciata: `garaDiOggi()` la tiene fino a tre ore
     * dopo). Vale per i pulsanti di hero e fascia, che con un link non
     * incorporabile aprono una scheda nuova.
     */
    private static function direttaVicina(?Game $gara): bool
    {
        return $gara !== null
            && LiveStream::externalUrl($gara->stream_url) !== null
            && now()->greaterThanOrEqualTo($gara->match_date->copy()->subMinutes(self::MINUTI_PRIMA_DELLA_DIRETTA));
    }

    private static function popupAttivo(): bool
    {
        return filter_var(SiteSetting::get('match_day_popup_attivo', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * I testi nella lingua della pagina. L'indirizzo vuoto resta vuoto: il
     * frontend ripiega sulla pagina Biglietteria nella lingua giusta.
     *
     * @return array{titolo: string, testo: string, pulsante: string, url: string}
     */
    private static function popup(): array
    {
        return [
            'titolo' => (string) SiteSetting::get('match_day_popup_titolo', ''),
            'testo' => (string) SiteSetting::get('match_day_popup_testo', ''),
            'pulsante' => (string) SiteSetting::get('match_day_popup_pulsante', ''),
            'url' => (string) SiteSetting::get('match_day_popup_url', ''),
        ];
    }

    /**
     * @return array{attivo: false, gara: null, in_casa: false, popup: null, popup_diretta: false, diretta_vicina: false}
     */
    private static function spento(): array
    {
        return ['attivo' => false, 'gara' => null, 'in_casa' => false, 'popup' => null, 'popup_diretta' => false, 'diretta_vicina' => false];
    }
}
