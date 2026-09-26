<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Observers\CacheInvalidationObserver;
use App\Services\StoricoPrezzi;
use Illuminate\Console\Command;

/**
 * Tiene aggiornato lo storico dei prezzi anche quando nessuno salva niente.
 *
 * Uno sconto programmato (`sale_start` / `sale_end`) cambia il prezzo che il
 * carrello fa pagare senza passare dal pannello, quindi senza che l'observer
 * se ne accorga. Il giro orario chiude quella finestra: al peggio lo storico
 * sposta l'inizio di uno sconto di un'ora, che su una finestra di 30 giorni
 * non cambia il prezzo da barrare.
 */
class RegistraIPrezzi extends Command
{
    protected $signature = 'prezzi:registra';

    protected $description = 'Registra nello storico i prezzi cambiati (sconti programmati compresi)';

    public function handle(StoricoPrezzi $storico, CacheInvalidationObserver $cache): int
    {
        $cambiato = null;

        Product::query()->each(function (Product $prodotto) use ($storico, &$cambiato) {
            if ($storico->registra($prodotto)) {
                $cambiato ??= $prodotto;
            }
        });

        // Uno sconto cominciato o finito da solo non salva il prodotto, quindi
        // l'observer non butta la vetrina: la card continuerebbe a dire IN
        // OFFERTA, con il barrato, mentre il carrello fa pagare il prezzo
        // pieno. Le chiavi sono le stesse per tutti i prodotti: basta un giro.
        if ($cambiato !== null) {
            $cache->saved($cambiato);
        }

        return self::SUCCESS;
    }
}
