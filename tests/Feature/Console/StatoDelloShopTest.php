<?php

namespace Tests\Feature\Console;

use App\Models\Auction;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `shop:stato` apre e chiude negozio e aste da riga di comando, senza passare
 * dal pannello: serve quando sono spenti per sbaglio e nessuno ha le
 * credenziali di un super admin sotto mano (21/09/2026).
 */
class StatoDelloShopTest extends TestCase
{
    use RefreshDatabase;

    private function valore(string $chiave): ?string
    {
        return SiteSetting::where('key', $chiave)->value('value');
    }

    public function test_senza_argomenti_dice_come_stanno_tutti_e_due(): void
    {
        SiteSetting::set('shop.enabled', '0');
        SiteSetting::set('auctions.enabled', '1');

        $this->artisan('shop:stato')
            ->expectsOutputToContain('Il negozio è chiuso')
            ->expectsOutputToContain('Le aste sono attive')
            ->assertSuccessful();

        // E non scrive niente.
        $this->assertSame('0', $this->valore('shop.enabled'));
        $this->assertSame('1', $this->valore('auctions.enabled'));
    }

    public function test_apre_il_negozio_con_la_forma_di_prima(): void
    {
        SiteSetting::set('shop.enabled', '0');

        $this->artisan('shop:stato', ['interruttore' => 'aperto'])
            ->expectsOutput('Negozio aperto.')
            ->assertSuccessful();

        $this->assertSame('1', $this->valore('shop.enabled'));
    }

    public function test_chiude_il_negozio(): void
    {
        SiteSetting::set('shop.enabled', '1');

        $this->artisan('shop:stato', ['interruttore' => 'negozio', 'stato' => 'chiuso'])
            ->expectsOutput('Negozio chiuso.')
            ->assertSuccessful();

        $this->assertSame('0', $this->valore('shop.enabled'));
    }

    public function test_accende_le_aste_senza_toccare_il_negozio(): void
    {
        SiteSetting::set('shop.enabled', '0');
        SiteSetting::set('auctions.enabled', '0');

        $this->artisan('shop:stato', ['interruttore' => 'aste', 'stato' => 'aperte'])
            ->expectsOutput('Aste attive.')
            ->assertSuccessful();

        $this->assertSame('1', $this->valore('auctions.enabled'));
        $this->assertSame('0', $this->valore('shop.enabled'));
    }

    public function test_sospende_le_aste(): void
    {
        SiteSetting::set('auctions.enabled', '1');

        $this->artisan('shop:stato', ['interruttore' => 'auctions', 'stato' => 'chiuse'])
            ->expectsOutput('Aste sospese.')
            ->assertSuccessful();

        $this->assertSame('0', $this->valore('auctions.enabled'));
    }

    public function test_non_riscrive_uno_stato_che_e_gia_quello(): void
    {
        SiteSetting::set('shop.enabled', '1');
        $prima = SiteSetting::where('key', 'shop.enabled')->value('updated_at');

        $this->travel(2)->minutes();

        $this->artisan('shop:stato', ['interruttore' => 'apri'])
            ->expectsOutputToContain('era già aperto')
            ->assertSuccessful();

        $this->assertEquals($prima, SiteSetting::where('key', 'shop.enabled')->value('updated_at'));
    }

    public function test_uno_stato_che_non_esiste_non_tocca_niente(): void
    {
        SiteSetting::set('shop.enabled', '1');

        $this->artisan('shop:stato', ['interruttore' => 'negozio', 'stato' => 'forse'])
            ->expectsOutputToContain('Stato non riconosciuto')
            ->assertFailed();

        $this->assertSame('1', $this->valore('shop.enabled'));
    }

    public function test_un_interruttore_che_non_esiste_non_tocca_niente(): void
    {
        SiteSetting::set('shop.enabled', '1');

        $this->artisan('shop:stato', ['interruttore' => 'magazzino', 'stato' => 'chiuso'])
            ->expectsOutputToContain('Interruttore non riconosciuto')
            ->assertFailed();

        $this->assertSame('1', $this->valore('shop.enabled'));
    }

    public function test_la_riga_che_non_esiste_nasce_interruttore(): void
    {
        // `type` è ciò che rende un valore un interruttore: senza, il pannello
        // lo rilegge come testo e al frontend arriva la stringa "0", vera in
        // JavaScript. SiteSetting::set() non lo scrive.
        $this->assertNull(SiteSetting::where('key', 'auctions.enabled')->first());

        $this->artisan('shop:stato', ['interruttore' => 'aste', 'stato' => 'chiuse'])
            ->assertSuccessful();

        $riga = SiteSetting::where('key', 'auctions.enabled')->firstOrFail();
        $this->assertSame('0', $riga->value);
        $this->assertSame('boolean', $riga->type);
        $this->assertSame('auctions', $riga->group);
        $this->assertFalse(SiteSetting::getGroup('auctions')['enabled']);
    }

    public function test_scrive_sulla_riga_che_governa_davvero_la_sezione(): void
    {
        // Forma `gruppo` + chiave nuda: creare accanto la forma letterale
        // `shop.enabled` oscurerebbe questa riga senza cancellarla, perché in
        // SiteSetting::get() la chiave intera vince.
        SiteSetting::create(['key' => 'enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'shop']);

        $this->artisan('shop:stato', ['interruttore' => 'negozio', 'stato' => 'aperto'])
            ->expectsOutput('Negozio aperto.')
            ->assertSuccessful();

        $this->assertNull(SiteSetting::where('key', 'shop.enabled')->first());
        $this->assertSame('1', SiteSetting::where('key', 'enabled')->where('group', 'shop')->value('value'));
    }

    public function test_avvisa_quando_la_pagina_delle_aste_resta_vuota(): void
    {
        SiteSetting::set('auctions.enabled', '0');

        Auction::factory()->for(Product::factory())->create(['status' => 'draft']);

        $this->artisan('shop:stato', ['interruttore' => 'aste', 'stato' => 'attive'])
            ->expectsOutput('Aste attive.')
            ->expectsOutputToContain('la pagina delle aste è vuota')
            ->assertSuccessful();
    }

    public function test_la_pagina_delle_aste_esce_davvero_dal_404(): void
    {
        SiteSetting::set('auctions.enabled', '0');

        $this->get(route('shop.auctions.index'))->assertNotFound();

        $this->artisan('shop:stato', ['interruttore' => 'aste', 'stato' => 'attive'])->assertSuccessful();

        $this->get(route('shop.auctions.index'))->assertSuccessful();
    }

    public function test_la_pagina_dello_shop_riapre_davvero(): void
    {
        SiteSetting::set('shop.enabled', '0');

        $this->get(route('shop'))->assertInertia(fn ($page) => $page->component('Public/Shop/Maintenance'));

        $this->artisan('shop:stato', ['interruttore' => 'aperto'])->assertSuccessful();

        $this->get(route('shop'))->assertInertia(fn ($page) => $page->component('Public/Shop/Index'));
    }
}
