<?php

namespace Tests\Feature\Filament;

use App\Enums\AuctionStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Resources\AuctionResource\Pages\CreateAuction;
use App\Filament\Resources\AuctionResource\Pages\EditAuction;
use App\Models\Auction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Aste: quelle create dal pannello devono poter arrivare sul sito.
 *
 * `status` sta fuori da $fillable per non essere scrivibile in massa, e il
 * campo "Stato" del modulo finiva quindi nel nulla: l'asta restava in bozza e
 * la pagina pubblica — che elenca solo attive, programmate e concluse — non
 * mostrava niente. In redazione si vedeva solo "Nessuna asta disponibile".
 */
class AstaDalPannelloTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function l_asta_nasce_in_bozza_e_il_prodotto_esce_dallo_shop(): void
    {
        $prodotto = $this->prodotto();

        Livewire::actingAs($this->superAdmin())
            ->test(CreateAuction::class)
            ->fillForm([
                'product_id' => $prodotto->id,
                'title' => 'Maglia autografata',
                'status' => AuctionStatus::Draft->value,
                'starting_price' => 50,
                'start_date' => now()->addDay(),
                'end_date' => now()->addDays(7),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $asta = Auction::firstOrFail();

        $this->assertSame(AuctionStatus::Draft, $asta->status);
        $this->assertSame(ProductType::Auction, $prodotto->refresh()->type);

        $this->get(route('shop.auctions.index'))->assertOk()->assertInertia(
            fn ($page) => $page->where('auctions', []),
        );
    }

    #[Test]
    public function portare_l_asta_su_attiva_la_fa_comparire_sul_sito(): void
    {
        $asta = $this->asta();

        Livewire::actingAs($this->superAdmin())
            ->test(EditAuction::class, ['record' => $asta->getRouteKey()])
            ->fillForm(['status' => AuctionStatus::Active->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(AuctionStatus::Active, $asta->refresh()->status);

        $this->get(route('shop.auctions.index'))->assertOk()->assertInertia(
            fn ($page) => $page->has('auctions', 1)
                ->where('auctions.0.title', $asta->title)
                ->where('auctions.0.status', AuctionStatus::Active->value),
        );
    }

    #[Test]
    public function una_transizione_non_ammessa_non_passa_ma_non_perde_il_resto(): void
    {
        $asta = $this->asta();
        $asta->forceFill(['status' => AuctionStatus::Ended])->save();

        Livewire::actingAs($this->superAdmin())
            ->test(EditAuction::class, ['record' => $asta->getRouteKey()])
            ->fillForm(['title' => 'Titolo corretto'])
            ->call('save')
            ->assertHasNoFormErrors();

        $asta->refresh();

        $this->assertSame(AuctionStatus::Ended, $asta->status);
        $this->assertSame('Titolo corretto', $asta->title);
        $this->assertFalse($asta->cambiaStato(AuctionStatus::Active));
    }

    #[Test]
    public function una_transizione_non_ammessa_avvisa_la_redazione(): void
    {
        $asta = $this->asta();
        $asta->forceFill(['status' => AuctionStatus::Ended])->save();

        Livewire::actingAs($this->superAdmin())
            ->test(EditAuction::class, ['record' => $asta->getRouteKey()])
            ->fillForm(['status' => AuctionStatus::Active->value])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame(AuctionStatus::Ended, $asta->refresh()->status);
    }

    #[Test]
    public function cancellare_l_asta_riporta_il_prodotto_nello_shop(): void
    {
        $asta = $this->asta();
        $prodotto = $asta->product;

        ProductVariant::factory()->for($prodotto)->create(['size' => 'M', 'stock' => 3]);

        $this->assertSame(ProductType::Auction, $prodotto->refresh()->type);

        $asta->delete();

        $this->assertSame(ProductType::Variable, $prodotto->refresh()->type);
    }

    #[Test]
    public function un_prodotto_senza_varianti_torna_semplice(): void
    {
        $asta = $this->asta();
        $prodotto = $asta->product;

        $asta->delete();

        $this->assertSame(ProductType::Simple, $prodotto->refresh()->type);
    }

    #[Test]
    public function ripristinare_l_asta_toglie_di_nuovo_il_prodotto_dallo_shop(): void
    {
        $asta = $this->asta();
        $prodotto = $asta->product;

        $asta->delete();
        $this->assertSame(ProductType::Simple, $prodotto->refresh()->type);

        $asta->restore();

        $this->assertSame(ProductType::Auction, $prodotto->refresh()->type);
    }

    /**
     * Il prodotto che la redazione ha gia' rimesso in vendita non si tocca:
     * altrimenti cancellare una vecchia asta lo riporterebbe al tipo dedotto
     * dalle varianti, scavalcando la scelta fatta a mano.
     */
    #[Test]
    public function cancellare_l_asta_non_tocca_un_prodotto_gia_in_vendita(): void
    {
        $asta = $this->asta();
        $prodotto = $asta->product;

        $prodotto->update(['type' => ProductType::Simple]);
        ProductVariant::factory()->for($prodotto)->create(['size' => 'L', 'stock' => 2]);

        $asta->delete();

        $this->assertSame(ProductType::Simple, $prodotto->refresh()->type);
    }

    private function asta(): Auction
    {
        $asta = Auction::factory()->create(['product_id' => $this->prodotto()->id]);

        $asta->forceFill(['status' => AuctionStatus::Draft])->save();

        return $asta->refresh();
    }

    private function prodotto(): Product
    {
        return Product::factory()->create([
            'product_category_id' => ProductCategory::factory(),
            'is_active' => true,
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user->refresh();
    }
}
