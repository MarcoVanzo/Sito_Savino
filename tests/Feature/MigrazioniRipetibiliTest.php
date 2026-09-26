<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Su MySQL il DDL non e' transazionale: una migrazione interrotta a meta'
 * lascia le prime colonne create e la riga di `migrations` mancante, e al
 * deploy successivo `migrate --force` (in `start.sh`) riparte da capo. Senza
 * guardie si ferma su "Duplicate column" / "Table already exists" e il
 * container non si avvia.
 */
class MigrazioniRipetibiliTest extends TestCase
{
    use RefreshDatabase;

    private function migrazione(string $nome): object
    {
        return require database_path('migrations/'.$nome.'.php');
    }

    public function test_le_migrazioni_del_25_09_si_possono_rilanciare(): void
    {
        $this->migrazione('2026_09_25_210000_etichette_e_personalizzazione_dei_prodotti')->up();
        $this->migrazione('2026_09_25_211000_storico_dei_prezzi')->up();
        $this->migrazione('2026_09_25_214000_richieste_di_recesso')->up();

        $this->assertTrue(Schema::hasTable('richieste_di_recesso'));
        $this->assertTrue(Schema::hasColumn('order_items', 'supplemento_personalizzazione'));
    }

    public function test_una_migrazione_interrotta_a_meta_riparte_dalle_colonne_mancanti(): void
    {
        // Come se il giro precedente si fosse fermato dopo `products`.
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['personalizzazione', 'supplemento_personalizzazione']);
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('con_personalizzazione');
        });

        $this->migrazione('2026_09_25_210000_etichette_e_personalizzazione_dei_prodotti')->up();

        $this->assertTrue(Schema::hasColumn('cart_items', 'con_personalizzazione'));
        $this->assertTrue(Schema::hasColumn('order_items', 'personalizzazione'));
        $this->assertTrue(Schema::hasColumn('order_items', 'supplemento_personalizzazione'));
    }
}
