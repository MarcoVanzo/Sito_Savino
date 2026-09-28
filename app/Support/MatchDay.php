<?php

namespace App\Support;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Builder;

/**
 * La modalità Match Day della homepage: fascia «Oggi si gioca», sezione della
 * partita in tema scuro e pop-up verso la biglietteria.
 *
 * La accende l'impostazione `match_day_modalita` (Impostazioni → Homepage):
 * `auto` nel giorno di una gara della società, fino a tre ore dal fischio
 * d'inizio; `off` la spegne; `on` la accende a mano **solo per il giorno in cui
 * la si sceglie** (`match_day_acceso_il`): dimenticata accesa, la home direbbe
 * «Oggi si gioca» per settimane.
 *
 * Il pop-up vende biglietti: in automatico compare solo per le gare in casa e
 * prima del fischio d'inizio. Acceso a mano, decide la redazione.
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

    /**
     * @return array{attivo: bool, gara: ?Game, in_casa: bool, popup: ?array{titolo: string, testo: string, pulsante: string, url: string}}
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

        return [
            'attivo' => true,
            'gara' => $gara,
            'in_casa' => $inCasa,
            'popup' => self::popupAttivo() && ($modalita === self::ACCESO || ($inCasa && $prima))
                ? self::popup()
                : null,
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
     * La gara della società in programma oggi, anche se è già cominciata:
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
            ->where(function (Builder $query) {
                $query->whereHas('homeTeam', fn (Builder $team) => $team->where('is_internal', true))
                    ->orWhereHas('awayTeam', fn (Builder $team) => $team->where('is_internal', true));
            })
            ->orderBy('match_date')
            ->first();
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
     * @return array{attivo: false, gara: null, in_casa: false, popup: null}
     */
    private static function spento(): array
    {
        return ['attivo' => false, 'gara' => null, 'in_casa' => false, 'popup' => null];
    }
}
