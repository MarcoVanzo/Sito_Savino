<?php

namespace App\Console\Commands;

use App\Models\Product;
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

    public function handle(StoricoPrezzi $storico): int
    {
        Product::query()->each(fn (Product $prodotto) => $storico->registra($prodotto));

        return self::SUCCESS;
    }
}
