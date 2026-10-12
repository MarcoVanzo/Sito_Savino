<?php

namespace App\Services\Diretta;

use App\Enums\StatoDellaDiretta;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Support\Diretta\ImpostazioniDellaDiretta;
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
 */
class RegiaDellaDiretta
{
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
                $s->ciclo_fine = $s->ciclo_inizio->copy()->addHours(3);
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

    public function riaccendi(LiveSession $sessione): bool
    {
        return $this->cambia($sessione, fn (LiveSession $s) => $this->tornaInOnda($s));
    }

    /**
     * Le pause già finite tornano in onda. Il browser lo sa già da
     * `fino_alle`: questa è la rete di sicurezza per chi apre la diretta dopo.
     */
    public function chiudiLePauseScadute(): int
    {
        $chiuse = 0;

        LiveSession::query()
            ->where('stato', StatoDellaDiretta::Pausa)
            ->where('pausa_fino_alle', '<=', now())
            ->each(function (LiveSession $sessione) use (&$chiuse): void {
                $this->cambia($sessione, function (LiveSession $s): void {
                    if ($s->stato === StatoDellaDiretta::Pausa && $s->pausa_fino_alle?->isPast()) {
                        $this->tornaInOnda($s);
                    }
                });
                $chiuse++;
            });

        return $chiuse;
    }

    /**
     * Rilegge la riga sotto lock, applica il cambio, alza la versione e
     * ripubblica. Restituisce se il JSON è stato scritto.
     *
     * @param  Closure(LiveSession): mixed  $cambio
     */
    private function cambia(LiveSession $sessione, Closure $cambio): bool
    {
        $aggiornata = DB::transaction(function () use ($sessione, $cambio): LiveSession {
            $riga = LiveSession::query()->lockForUpdate()->findOrFail($sessione->id);
            $cambio($riga);
            $riga->versione++;
            $riga->save();

            return $riga;
        });

        $sessione->setRawAttributes($aggiornata->getAttributes(), true);

        return $this->statoPubblico->pubblica($aggiornata);
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
