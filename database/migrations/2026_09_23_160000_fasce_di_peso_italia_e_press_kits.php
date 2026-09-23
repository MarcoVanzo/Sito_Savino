<?php

use App\Models\MenuItem;
use App\Models\ShippingZone;
use Illuminate\Database\Migrations\Migration;

/**
 * Due valori comunicati dalla società il 23/09/2026.
 *
 * Le fasce di peso della spedizione in Italia: in produzione c'era solo la
 * prima (0-2 kg a 7,50 €), inserita a mano dal pannello. L'ultima fascia non
 * ha limite ("oltre 30 kg"), come prevede `ShippingZone::fasceOrdinate()`.
 * La soglia della spedizione gratuita resta quella della zona e viene prima.
 *
 * La voce inglese di "Cartelle Stampa" diventa "Press Kits".
 *
 * A guardie (§14 di CLAUDE.md): le fasce si scrivono solo se la zona ha
 * ancora nessuna fascia o la sola 0-2 kg a 7,50 €, la voce solo se dice
 * ancora "Press Folders". Passando dai model, le cache di zone e menu si
 * svuotano da sole.
 */
return new class extends Migration
{
    private const FASCE_ITALIA = [
        ['max_weight' => '2', 'rate' => '7.50'],
        ['max_weight' => '5', 'rate' => '9.00'],
        ['max_weight' => '10', 'rate' => '10.50'],
        ['max_weight' => '20', 'rate' => '12.50'],
        ['max_weight' => '30', 'rate' => '14.00'],
        ['max_weight' => null, 'rate' => '26.00'],
    ];

    public function up(): void
    {
        $this->fasceDellItalia();
        $this->pressKits();
    }

    private function fasceDellItalia(): void
    {
        $zona = ShippingZone::query()->get()
            ->first(fn (ShippingZone $z) => $z->countries === ['IT']);

        if ($zona === null || ! $this->fasceAncoraDaCompilare($zona->weight_rates)) {
            return;
        }

        $zona->weight_rates = self::FASCE_ITALIA;
        $zona->save();
    }

    private function fasceAncoraDaCompilare(mixed $fasce): bool
    {
        if (empty($fasce)) {
            return true;
        }

        if (! is_array($fasce) || count($fasce) !== 1) {
            return false;
        }

        $unica = reset($fasce);

        return (float) ($unica['max_weight'] ?? -1) === 2.0
            && (float) ($unica['rate'] ?? -1) === 7.5;
    }

    private function pressKits(): void
    {
        MenuItem::query()->get()
            ->filter(fn (MenuItem $voce) => trim((string) $voce->url, '/') === 'comunicazione/cartelle'
                && $voce->getTranslation('label', 'en', false) === 'Press Folders')
            ->each(function (MenuItem $voce) {
                $voce->setTranslation('label', 'en', 'Press Kits');
                $voce->save();
            });
    }

    /**
     * Non si annulla: sono i valori comunicati dalla società.
     */
    public function down(): void
    {
        // no-op documentato
    }
};
