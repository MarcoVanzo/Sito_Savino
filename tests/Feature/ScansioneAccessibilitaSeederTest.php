<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ScansioneAccessibilitaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * I dati del lavoro `flussi` della scansione di accessibilita': un prodotto a
 * taglie comprabile e un'asta vinta con il checkout aperto, sempre con gli
 * stessi identificativi che lo script usa. Rilanciarlo non duplica niente, e
 * in produzione non parte.
 */
class ScansioneAccessibilitaSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function prepara_prodotto_e_asta_vinta_ed_e_ripetibile(): void
    {
        $this->seed(ScansioneAccessibilitaSeeder::class);
        $this->seed(ScansioneAccessibilitaSeeder::class);

        $prodotto = Product::shoppable()->where('slug', ScansioneAccessibilitaSeeder::PRODOTTO)->sole();
        $this->assertCount(3, $prodotto->variants);

        $asta = Auction::where('winner_checkout_token', ScansioneAccessibilitaSeeder::TOKEN_ASTA)->sole();
        $this->assertSame(User::where('email', ScansioneAccessibilitaSeeder::EMAIL)->value('id'), $asta->winner_user_id);
        $this->assertTrue($asta->winner_checkout_deadline->isFuture());
    }

    #[Test]
    public function in_produzione_si_rifiuta(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->app->make(ScansioneAccessibilitaSeeder::class)->run();
    }
}
