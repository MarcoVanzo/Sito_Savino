<?php

namespace App\Services\Diretta;

use App\Enums\StatoDellaDiretta;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Support\Diretta\ImpostazioniDellaDiretta;
use App\Support\MatchDay;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Le azioni della regia a bordo campo: spot tra i set, una creatività a
 * comando, ciclo sospeso o ripreso, tutto spento.
 *
 * Il sistema funziona anche senza regia (il ciclo lo calcola il browser):
 * qui si cambia lo stato, si alza la versione e si ripubblica il JSON letto
 * dal sito. Ogni azione salva la diretta, quindi finisce nel registro delle
 * attività con il suo autore.
 *
 * È l'unica porta per chi scrive la diretta (regia, «Imposta», gara spostata,
 * creatività cambiate, interruttore): tutti passano da `cambia()`, che tiene
 * il lock fino alla scrittura del JSON. Così due scritture insieme non
 * escono con la stessa versione, né in ordine inverso su Spaces.
 */
class RegiaDellaDiretta
{
    /**
     * Quello che un cambio restituisce quando sotto lock scopre di non avere
     * niente da fare. Non `false`: `fn ($s) => $s->ciclo_attivo = false`
     * restituisce il valore assegnato.
     */
    public const NESSUN_CAMBIO = 'nessun-cambio';

    public function __construct(private StatoPubblicoDellaDiretta $statoPubblico) {}

    /** Le cornici della gara a rotazione, per la durata predefinita. */
    public function pausaTraISet(LiveSession $sessione): bool
    {
        return $this->cambia($sessione, function (LiveSession $s): void {
            $this->inPausa($s, null, $s->pausa_durata_predefinita_secondi);
        });
    }

    /** Una creatività scelta, per i secondi indicati. */
    public function mostra(LiveSession $sessione, LiveCreative $creativita, int $secondi): bool
    {
        $secondi = max(ImpostazioniDellaDiretta::PAUSA_DURATA_MINIMA, min(ImpostazioniDellaDiretta::PAUSA_DURATA_MASSIMA, $secondi));

        return $this->cambia($sessione, function (LiveSession $s) use ($creativita, $secondi): void {
            $this->inPausa($s, $creativita->id, $secondi);
        });
    }

    public function finePausa(LiveSession $sessione): bool
    {
        return $this->cambia($sessione, function (LiveSession $s): void {
            $this->tornaInOnda($s);
        });
    }

    public function sospendiCiclo(LiveSession $sessione): bool
    {
        return $this->cambia($sessione, fn (LiveSession $s) => $s->ciclo_attivo = false);
    }

    public function riprendiCiclo(LiveSession $sessione): bool
    {
        return $this->cambia($sessione, fn (LiveSession $s) => $s->ciclo_attivo = true);
    }

    /** Il ciclo si conta da adesso: il primo sponsor esce fra un intervallo. */
    public function ripartiDaAdesso(LiveSession $sessione): bool
    {
        return $this->cambia($sessione, function (LiveSession $s): void {
            $s->ciclo_inizio = now()->addMinutes($s->ciclo_ogni_minuti);
            $s->ciclo_attivo = true;

            if ($s->ciclo_fine === null || $s->ciclo_fine->lessThanOrEqualTo($s->ciclo_inizio)) {
                $s->ciclo_fine = $s->ciclo_inizio->copy()->addHours(MatchDay::ORE_DOPO_L_INIZIO);
            }
        });
    }

    /** Nessuno sponsor durante la gara: niente spot, ciclo fermo. */
    public function spegniTutto(LiveSession $sessione): bool
    {
        return $this->cambia($sessione, function (LiveSession $s): void {
            $this->tornaInOnda($s);
            $s->stato = StatoDellaDiretta::Inattiva;
            $s->ciclo_attivo = false;
        });
    }

    /** Gli sponsor tornano: anche il ciclo, che «Spegni tutto» aveva fermato. */
    public function riaccendi(LiveSession $sessione): bool
    {
        return $this->cambia($sessione, function (LiveSession $s): void {
            $this->tornaInOnda($s);
            $s->ciclo_attivo = true;
        });
    }

    /**
     * La gara ha cambiato orario (sync della Lega o della CEV, rinvio): il
     * ciclo si sposta dello stesso scarto, così resta anche un eventuale
     * «Riparti da adesso» della regia relativo alla gara.
     */
    public function seguiLaGara(LiveSession $sessione, CarbonInterface $prima, CarbonInterface $adesso): bool
    {
        $scarto = $adesso->getTimestamp() - $prima->getTimestamp();

        if ($scarto === 0) {
            return false;
        }

        return $this->cambia($sessione, function (LiveSession $s) use ($scarto): void {
            $s->ciclo_inizio = $s->ciclo_inizio?->copy()->addSeconds($scarto);
            $s->ciclo_fine = $s->ciclo_fine?->copy()->addSeconds($scarto);
        });
    }

    /**
     * Riscrive il JSON con una versione nuova: chi ha la diretta aperta
     * accetta solo versioni più alte, e senza non vedrebbe una creatività
     * spenta o cambiata (o l'interruttore spento) fino a fine gara.
     */
    public function ripubblica(LiveSession $sessione): bool
    {
        return $this->cambia($sessione, fn () => null);
    }

    /**
     * Ripubblica le dirette delle gare da ieri ai prossimi giorni: sono
     * poche, e cercare quelle che usano una creatività non funziona dopo una
     * cancellazione (le righe ponte se ne vanno con lei).
     */
    public function ripubblicaLeDiretteVicine(): int
    {
        $pubblicate = 0;

        LiveSession::query()
            ->whereHas('game', fn ($gara) => $gara->whereBetween('match_date', [
                now()->subDay(),
                now()->addDays(StatoPubblicoDellaDiretta::GIORNI_DA_RIPUBBLICARE),
            ]))
            ->each(function (LiveSession $sessione) use (&$pubblicate): void {
                $pubblicate += (int) $this->ripubblica($sessione);
            });

        return $pubblicate;
    }

    /**
     * Le pause già finite tornano in onda. Il browser lo sa già da
     * `fino_alle`: questa è la rete di sicurezza per chi apre la diretta dopo.
     * Si contano solo quelle chiuse davvero: sotto lock la regia può averla
     * appena rinnovata.
     */
    public function chiudiLePauseScadute(): int
    {
        $chiuse = 0;

        LiveSession::query()
            ->where('stato', StatoDellaDiretta::Pausa)
            ->where('pausa_fino_alle', '<=', now())
            ->each(function (LiveSession $sessione) use (&$chiuse): void {
                $this->cambia($sessione, function (LiveSession $s) use (&$chiuse): ?string {
                    if ($s->stato !== StatoDellaDiretta::Pausa || ! $s->pausa_fino_alle?->isPast()) {
                        return self::NESSUN_CAMBIO;
                    }

                    $this->tornaInOnda($s);
                    $chiuse++;

                    return null;
                });
            });

        return $chiuse;
    }

    /**
     * Rilegge la riga sotto lock, applica il cambio, alza la versione e
     * ripubblica tenendo il lock: un altro cambio aspetta che il JSON sia
     * scritto, quindi su Spaces non arriva mai una versione vecchia dopo una
     * nuova. Il cambio che restituisce `NESSUN_CAMBIO` non si salva né si
     * ripubblica. Restituisce se il JSON è stato scritto.
     *
     * @param  Closure(LiveSession): mixed  $cambio
     */
    public function cambia(LiveSession $sessione, Closure $cambio): bool
    {
        [$aggiornata, $pubblicato] = DB::transaction(function () use ($sessione, $cambio): array {
            $riga = LiveSession::query()->lockForUpdate()->findOrFail($sessione->id);

            if ($cambio($riga) === self::NESSUN_CAMBIO) {
                return [$riga, false];
            }

            $riga->versione++;
            $riga->save();

            return [$riga, $this->statoPubblico->pubblica($riga)];
        });

        $sessione->setRawAttributes($aggiornata->getAttributes(), true);
        $sessione->setRelations([]);

        return $pubblicato;
    }

    private function inPausa(LiveSession $s, ?int $creativita, int $secondi): void
    {
        $s->stato = StatoDellaDiretta::Pausa;
        $s->creativita_in_onda_id = $creativita;
        $s->pausa_dalle = now();
        $s->pausa_fino_alle = now()->addSeconds($secondi);
    }

    private function tornaInOnda(LiveSession $s): void
    {
        $s->stato = StatoDellaDiretta::InOnda;
        $s->creativita_in_onda_id = null;
        $s->pausa_dalle = null;
        $s->pausa_fino_alle = null;
    }
}
