<?php

use App\Models\SiteSetting;
use App\Support\CondizioniDiVendita;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'intestatario del conto per il bonifico, scritto per esteso.
 *
 * In produzione `shop.bank_transfer_beneficiary` valeva
 * «Pallavolo Scandicci Savino Del BeneSoc.  Sport. Dilett. A Resp. Li»: spazio
 * mancante dopo «Bene», doppio spazio e ragione sociale troncata. È il testo
 * che il cliente copia nel bonifico, sulla conferma d'ordine, nel promemoria e
 * (dal 30/09/2026) nella pagina di conferma delle aste. Diventa la ragione
 * sociale di CondizioniDiVendita::RAGIONE_SOCIALE, la stessa di condizioni e
 * informative.
 *
 * A guardia: si tocca solo il valore ancora rotto, riconosciuto dal «BeneSoc»
 * incollato (spazi normalizzati, anche quelli non separabili). Un intestatario
 * già corretto dal pannello resta com'è.
 */
return new class extends Migration
{
    private const DIFETTO = 'Pallavolo Scandicci Savino Del BeneSoc';

    public function up(): void
    {
        $righe = DB::table('site_settings')
            ->whereIn('key', ['shop.bank_transfer_beneficiary', 'bank_transfer_beneficiary'])
            ->get(['id', 'value']);

        $toccate = 0;

        foreach ($righe as $riga) {
            $normalizzato = trim((string) preg_replace('/\s+/u', ' ', (string) $riga->value));

            if (! str_starts_with($normalizzato, self::DIFETTO)) {
                continue;
            }

            DB::table('site_settings')->where('id', $riga->id)->update([
                'value' => CondizioniDiVendita::RAGIONE_SOCIALE,
                'updated_at' => now(),
            ]);
            $toccate++;
        }

        if ($toccate > 0) {
            SiteSetting::clearCache();
        }
    }

    /**
     * Non si annulla: il valore di prima era il difetto.
     */
    public function down(): void
    {
        // no-op documentato
    }
};
