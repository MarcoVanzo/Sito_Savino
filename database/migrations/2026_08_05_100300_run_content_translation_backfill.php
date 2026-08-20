<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Applica in produzione le traduzioni inglesi mancanti dei contenuti CMS.
 *
 * Il lavoro vero lo fa `content:translate-missing`, che resta disponibile per
 * essere rilanciato quando la redazione aggiunge contenuti nuovi. Qui lo
 * invochiamo una volta perché il deploy esegue `migrate --force` all'avvio:
 * senza questo passaggio servirebbe un accesso manuale alla console di
 * produzione per vedere il sito inglese finalmente tradotto.
 *
 * Il comando è idempotente e non sovrascrive mai una traduzione fatta in
 * redazione, quindi rieseguirlo è innocuo. Il suo exit code segnala i contenuti
 * ancora senza traduzione (le news, che non sono in questo giro): è
 * un'informazione, non un errore, e non deve far fallire il deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('content:translate-missing');
    }

    /**
     * Non reversibile: non sapremmo distinguere le traduzioni scritte qui da
     * quelle inserite dalla redazione subito dopo.
     */
    public function down(): void {}
};
