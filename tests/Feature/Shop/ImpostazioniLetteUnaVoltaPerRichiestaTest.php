<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sentry, 2/10/2026: N+1 su `select * from cache` nella categoria dello shop.
 *
 * In produzione la cache sta nel database: ogni SiteSetting::get() (guida
 * taglie, peso di spedizione) era una SELECT, ripetuta per ogni prodotto.
 * Le impostazioni ora restano in memoria per tutta la richiesta.
 */
class ImpostazioniLetteUnaVoltaPerRichiestaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'database']);
    }

    private function selectSullaCache(ProductCategory $categoria): int
    {
        $conteggio = 0;
        DB::listen(function ($query) use (&$conteggio) {
            if (preg_match('/^select .* from [`"]cache[`"]/i', $query->sql)) {
                $conteggio++;
            }
        });

        $this->get(route('shop.category', ['category' => $categoria->slug]))->assertOk();

        return $conteggio;
    }

    #[Test]
    public function le_query_sulla_cache_non_crescono_con_i_prodotti(): void
    {
        SiteSetting::set('shop.size_guides', json_encode(['guide/taglie.pdf']));

        $poche = ProductCategory::factory()->create();
        Product::factory()->count(2)->create(['product_category_id' => $poche->id]);

        $molte = ProductCategory::factory()->create();
        Product::factory()->count(10)->create(['product_category_id' => $molte->id]);

        // Si scalda la cache (impostazioni, menu) su una terza categoria; le
        // due misurate non sono mai state aperte, cosi' nessuna arriva dalla
        // cache delle pagine.
        $this->selectSullaCache(ProductCategory::factory()->create());
        $this->app->forgetScopedInstances();

        $conPoche = $this->selectSullaCache($poche);
        $this->app->forgetScopedInstances();
        $conMolte = $this->selectSullaCache($molte);

        $this->assertSame($conPoche, $conMolte);
    }

    #[Test]
    public function un_impostazione_salvata_si_rilegge_subito_nella_stessa_richiesta(): void
    {
        SiteSetting::set('shop.support_email', 'prima@example.test');
        $this->assertSame('prima@example.test', SiteSetting::get('shop.support_email'));

        SiteSetting::set('shop.support_email', 'dopo@example.test');
        $this->assertSame('dopo@example.test', SiteSetting::get('shop.support_email'));
    }
}
