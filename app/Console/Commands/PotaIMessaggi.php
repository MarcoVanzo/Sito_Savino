<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use Illuminate\Console\Command;

/**
 * Cancella i messaggi del modulo contatti e le richieste di accredito scaduti.
 *
 * L'informativa promette ventiquattro mesi dall'ultimo contatto, e finché non
 * c'è stato questo comando quella riga era l'unica conservazione dichiarata che
 * nessuno applicava: i messaggi restavano per sempre. Le altre hanno tutte il
 * loro comando — `consensi:pota`, `activity-log:prune`, `carts:prune-expired`.
 *
 * Si conta dalla **data del messaggio**, non da `updated_at`: quello cambia
 * quando la redazione lo segna come letto o risposto, e basterebbe riaprire un
 * elenco per rimandare in là la scadenza di tutto l'archivio.
 *
 * Cancella la riga intera, `extra_data` compreso: lì dentro stanno testata,
 * ruolo, gara e telefono di chi ha chiesto un accredito.
 */
class PotaIMessaggi extends Command
{
    protected $signature = 'messaggi:pota
        {--mesi=24 : Da quanti mesi in là un messaggio si cancella}
        {--prova : Dice quanti ne toglierebbe senza cancellare niente}';

    protected $description = 'Cancella messaggi e richieste di accredito più vecchi di N mesi';

    public function handle(): int
    {
        $mesi = max(1, (int) $this->option('mesi'));
        $limite = now()->subMonths($mesi);

        $scaduti = ContactMessage::query()->where('created_at', '<', $limite);

        if ($this->option('prova')) {
            $quanti = $scaduti->count();

            $this->info($quanti === 0
                ? 'Nessun messaggio da togliere.'
                : 'Da togliere: '.$quanti.($quanti === 1 ? ' messaggio' : ' messaggi').', più vecchi del '.$limite->format('d/m/Y').'.');

            return self::SUCCESS;
        }

        $cancellati = $scaduti->delete();

        if ($cancellati === 0) {
            $this->info('Nessun messaggio da togliere.');

            return self::SUCCESS;
        }

        $this->info($cancellati.' '.$this->parola($cancellati).', più vecchi del '.$limite->format('d/m/Y').'.');

        return self::SUCCESS;
    }

    private function parola(int $quanti): string
    {
        return $quanti === 1 ? 'messaggio cancellato' : 'messaggi cancellati';
    }
}
