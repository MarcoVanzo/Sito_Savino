<?php

use App\Models\LiveCreative;
use Illuminate\Database\Migrations\Migration;

/**
 * Refuso nel testo alternativo delle grafiche della diretta Savino Del Bene
 * x Il Bisonte («Every shipmente»): è quello che legge lo screen reader. A
 * guardie: si tocca solo il valore ancora sbagliato. Passando dal modello,
 * l'observer ripubblica il JSON delle dirette e svuota le pagine in cache.
 */
return new class extends Migration
{
    private const SBAGLIATO = 'Savino Del Bene - Every shipmente has a story to tell';

    private const GIUSTO = 'Savino Del Bene - Every shipment has a story to tell';

    public function up(): void
    {
        LiveCreative::query()
            ->where('testo_alternativo', 'like', '%Every shipmente%')
            ->each(function (LiveCreative $creativita): void {
                if ($creativita->getTranslation('testo_alternativo', 'it', false) !== self::SBAGLIATO) {
                    return;
                }

                $creativita->setTranslation('testo_alternativo', 'it', self::GIUSTO)->save();
            });
    }

    /** Nessun ritorno: rimettere un refuso non serve a nessuno. */
    public function down(): void {}
};
