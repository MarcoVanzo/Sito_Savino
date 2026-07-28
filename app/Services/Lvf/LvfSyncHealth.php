<?php

namespace App\Services\Lvf;

use App\Models\SiteSetting;
use App\Services\AdminNotificationService;
use Illuminate\Support\Str;

/**
 * Tiene il conto dei fallimenti consecutivi della sincronizzazione con la Lega
 * e avvisa gli amministratori quando il guasto smette di essere un episodio.
 *
 * Il comando gira ogni ora: il sito della Lega ha cali temporanei e un avviso a
 * ogni singhiozzo verrebbe imparato a ignorare. Si avvisa solo dopo N giri a
 * vuoto di fila, e poi si ripete di rado, perché finché il guasto dura il
 * comando continua a fallire una volta all'ora.
 *
 * Lo stato sta in `site_settings` e non in cache perché `start.sh` esegue
 * `cache:clear` a ogni deploy: un contatore in cache si azzererebbe a ogni
 * rilascio e un guasto che dura giorni non raggiungerebbe mai la soglia.
 * La riga non appartiene a nessun gruppo pubblico, quindi non finisce nelle
 * impostazioni esposte al frontend.
 */
class LvfSyncHealth
{
    /**
     * Chiave della riga di `site_settings` che conserva il contatore.
     */
    public const FAILURES_KEY = 'lvf.sync_consecutive_failures';

    public function __construct(
        private readonly AdminNotificationService $notifications,
    ) {}

    /**
     * Una sincronizzazione riuscita chiude la serie di fallimenti.
     *
     * Si scrive solo se c'è davvero qualcosa da azzerare: `SiteSetting::set()`
     * invalida la cache di tutte le impostazioni del sito, e non ha senso farlo
     * ogni ora quando va tutto bene.
     */
    public function recordSuccess(): void
    {
        if ($this->consecutiveFailures() > 0) {
            SiteSetting::set(self::FAILURES_KEY, '0');
        }
    }

    /**
     * Registra un fallimento e avvisa gli amministratori se la serie è ormai
     * abbastanza lunga da non essere un caso.
     *
     * @param  string  $reason  messaggio dell'errore, per dare all'avviso un aggancio concreto
     * @return int fallimenti consecutivi dopo questo
     */
    public function recordFailure(string $reason): int
    {
        $failures = $this->consecutiveFailures() + 1;

        SiteSetting::set(self::FAILURES_KEY, (string) $failures);

        if ($this->shouldAlert($failures)) {
            $this->notifications->notifyLvfSyncFailing($failures, Str::limit($reason, 200));
        }

        return $failures;
    }

    /**
     * Il contatore si legge dalla tabella e non da `SiteSetting::get()`: quello
     * passa dalla cache delle impostazioni, che qui darebbe un valore vecchio.
     */
    public function consecutiveFailures(): int
    {
        return (int) SiteSetting::where('key', self::FAILURES_KEY)->value('value');
    }

    /**
     * Si avvisa quando si tocca la soglia e poi a intervalli regolari, non a
     * ogni ulteriore fallimento: con il comando orario significherebbe una
     * notifica all'ora finché la Lega non torna su.
     */
    private function shouldAlert(int $failures): bool
    {
        $threshold = max(1, (int) config('services.lvf.failure_alert_threshold', 3));

        if ($failures < $threshold) {
            return false;
        }

        $repeatEvery = max(1, (int) config('services.lvf.failure_alert_repeat_every', 24));

        return ($failures - $threshold) % $repeatEvery === 0;
    }
}
