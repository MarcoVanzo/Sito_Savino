<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le impostazioni dello Shop svuotate dal primo salvataggio del pannello.
 *
 * In produzione le righe `shop.*` e `auctions.*` non esistevano: la pagina si
 * apriva con tutti i campi vuoti e il 21/09/2026 alle 16:05 il primo Salva le
 * ha create così com'erano — vuote. Il modulo è stato corretto lo stesso
 * giorno (si apre sui valori di partenza), ma le righe già scritte restano, e
 * vuote non significano "nessun limite": significano zero.
 *
 * `shop.max_qty_per_product` a '' vale 0 pezzi per prodotto,
 * `shop.cart_expiry_days` a '' fa scadere il carrello lo stesso giorno,
 * `shop.active_payment_gateways` a '' non offre alcun metodo di pagamento al
 * checkout. Qui si riportano ai valori di `database/data/impostazioni_shop.php`
 * — gli stessi del seeder — le sole chiavi rimaste vuote.
 *
 * Due esclusioni volute:
 *
 * - gli interruttori (`type` `boolean`): `shop.enabled` e `auctions.enabled`
 *   hanno un valore, non sono vuoti, e riaccendere il negozio è una decisione
 *   della redazione, non di una migrazione;
 * - `shop.free_shipping_threshold`, dove vuoto è una scelta: senza soglia
 *   globale vale quella della zona di spedizione (100 € per l'Italia), e
 *   scriverci i 50 € del file dati farebbe promettere al carrello una
 *   spedizione gratuita che il checkout non concede.
 *
 * Il `type` invece si riallinea sempre: è la colonna che distingue un
 * interruttore da un testo (SiteSetting::valoreTipizzato) e il salvataggio del
 * pannello, che non lo tocca, le ha create tutte `text`.
 */
return new class extends Migration
{
    /**
     * Vuoto è una scelta, non una mancanza: vedi il commento in testa.
     *
     * @var list<string>
     */
    private const SENZA_RIPIEGO = ['shop.free_shipping_threshold'];

    public function up(): void
    {
        $toccate = 0;

        foreach (SiteSetting::definizioniDelloShop() as $definizione) {
            $riga = DB::table('site_settings')->where('key', $definizione['key'])->first();

            // Le chiavi che in archivio non ci sono non si creano qui. La forma
            // letterale `shop.x` vince, in SiteSetting::get(), sulla forma
            // `x` + colonna `group`: scriverla oscurerebbe in silenzio un
            // valore salvato nell'altra forma, con un valore di partenza al
            // posto di quello scelto in redazione. Senza riga vale il ripiego
            // del codice, e il pannello si apre comunque sul valore di
            // partenza (ShopSettingsPage::valoriPredefiniti).
            if (! $riga) {
                continue;
            }

            $aggiornamenti = [];

            if ($this->vaRiempita($definizione, $riga)) {
                $aggiornamenti['value'] = $definizione['value'];
            }

            if ($riga->type !== $definizione['type']) {
                $aggiornamenti['type'] = $definizione['type'];
            }

            if ($aggiornamenti === []) {
                continue;
            }

            $aggiornamenti['updated_at'] = now();

            DB::table('site_settings')->where('id', $riga->id)->update($aggiornamenti);
            $toccate++;
        }

        if ($toccate > 0) {
            SiteSetting::clearCache();
        }
    }

    /**
     * La riga ha perso il suo valore e va riscritta.
     *
     * A guardia: si tocca solo ciò che è ancora vuoto, così la migrazione non
     * sovrascrive un valore che nel frattempo la redazione ha messo a mano.
     */
    private function vaRiempita(array $definizione, object $riga): bool
    {
        if (in_array($definizione['key'], self::SENZA_RIPIEGO, true)) {
            return false;
        }

        if ($definizione['type'] === 'boolean' || (string) $definizione['value'] === '') {
            return false;
        }

        return $riga->value === null || trim((string) $riga->value) === '';
    }

    /**
     * Non si annulla: il valore di prima era la stringa vuota, ed è il difetto
     * da cui nasce questa migrazione.
     */
    public function down(): void
    {
        // no-op documentato
    }
};
