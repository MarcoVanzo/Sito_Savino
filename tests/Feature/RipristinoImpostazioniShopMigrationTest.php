<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le impostazioni dello Shop svuotate dal primo salvataggio del pannello.
 *
 * In produzione le righe `shop.*` non esistevano: il modulo si apriva vuoto e
 * il primo Salva le ha create vuote. Vuoto però non vuol dire "nessun limite":
 * `shop.max_qty_per_product` a '' vale zero pezzi, e senza gateway attivi il
 * checkout non offre alcun pagamento. Qui si verifica che la migrazione
 * riscriva solo ciò che è rimasto vuoto, che non decida al posto della
 * redazione e che sia rieseguibile.
 */
class RipristinoImpostazioniShopMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function eseguiLaMigrazione(): void
    {
        (require database_path('migrations/2026_09_22_100000_ripristina_le_impostazioni_dello_shop_rimaste_vuote.php'))->up();
    }

    private function scrivi(string $chiave, ?string $valore, string $tipo = 'text'): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['key' => $chiave],
            ['value' => $valore, 'type' => $tipo, 'group' => 'general', 'updated_at' => now(), 'created_at' => now()],
        );

        SiteSetting::clearCache();
    }

    private function valore(string $chiave): ?string
    {
        return DB::table('site_settings')->where('key', $chiave)->value('value');
    }

    public function test_le_chiavi_vuote_tornano_al_valore_di_partenza(): void
    {
        $this->scrivi('shop.max_qty_per_product', '');
        $this->scrivi('shop.cart_expiry_days', '');
        $this->scrivi('shop.active_payment_gateways', '');
        $this->scrivi('auctions.min_bid_increment', '');

        $this->eseguiLaMigrazione();

        $this->assertSame('10', $this->valore('shop.max_qty_per_product'));
        $this->assertSame('7', $this->valore('shop.cart_expiry_days'));
        $this->assertSame('stripe,paypal,bank_transfer', $this->valore('shop.active_payment_gateways'));
        $this->assertSame('5', $this->valore('auctions.min_bid_increment'));
    }

    public function test_non_tocca_quello_che_la_redazione_ha_scritto(): void
    {
        $this->scrivi('shop.max_qty_per_product', '3');
        $this->scrivi('shop.active_payment_gateways', 'bank_transfer');

        $this->eseguiLaMigrazione();

        $this->assertSame('3', $this->valore('shop.max_qty_per_product'));
        $this->assertSame('bank_transfer', $this->valore('shop.active_payment_gateways'));
    }

    public function test_non_riaccende_il_negozio_ne_le_aste(): void
    {
        // Spegnere lo shop è una decisione della redazione: la migrazione
        // rimette i numeri, non gli interruttori.
        $this->scrivi('shop.enabled', '0');
        $this->scrivi('auctions.enabled', '0');

        $this->eseguiLaMigrazione();

        $this->assertSame('0', $this->valore('shop.enabled'));
        $this->assertSame('0', $this->valore('auctions.enabled'));
    }

    public function test_la_soglia_della_spedizione_gratuita_resta_vuota(): void
    {
        // Vuota significa "vale la soglia della zona di spedizione": scriverci
        // i 50 € del file dati farebbe promettere al carrello una spedizione
        // gratuita che il checkout, che applica i 100 € dell'Italia, non dà.
        $this->scrivi('shop.free_shipping_threshold', '');

        $this->eseguiLaMigrazione();

        $this->assertSame('', $this->valore('shop.free_shipping_threshold'));
    }

    public function test_riallinea_il_tipo_dichiarato_dal_file_dati(): void
    {
        // Il salvataggio del pannello non scrive la colonna `type`, e un
        // interruttore nato come `text` non viene più letto come booleano.
        $this->scrivi('shop.enabled', '1', 'text');

        $this->eseguiLaMigrazione();

        $this->assertSame('boolean', DB::table('site_settings')->where('key', 'shop.enabled')->value('type'));
    }

    public function test_non_inventa_le_chiavi_che_in_archivio_non_ci_sono(): void
    {
        // La forma letterale `shop.x` vince su `x` + colonna `group`: crearla
        // qui oscurerebbe un valore salvato nell'altra forma. Senza riga vale
        // il ripiego del codice, e il modulo si apre sul valore di partenza.
        DB::table('site_settings')->where('key', 'shop.default_item_weight_kg')->delete();
        SiteSetting::clearCache();

        $this->eseguiLaMigrazione();

        $this->assertDatabaseMissing('site_settings', ['key' => 'shop.default_item_weight_kg']);
        $this->assertSame(0.5, (float) SiteSetting::get('shop.default_item_weight_kg', 0.5));
    }

    public function test_si_puo_rieseguire(): void
    {
        $this->scrivi('shop.max_qty_per_product', '');

        $this->eseguiLaMigrazione();
        $this->scrivi('shop.max_qty_per_product', '4');
        $this->eseguiLaMigrazione();

        $this->assertSame('4', $this->valore('shop.max_qty_per_product'));
    }
}
