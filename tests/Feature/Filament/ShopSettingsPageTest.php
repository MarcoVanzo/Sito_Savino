<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\Settings\ShopSettingsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Impostazioni Shop & Aste: salvare la pagina non deve spegnere il negozio.
 *
 * La redazione ha acceso le aste e si è ritrovata lo shop in manutenzione. Il
 * caso che conta è il salvataggio di un campo qualunque: la pagina scrive in
 * un colpo solo tutte le chiavi dei gruppi `shop` e `auctions`, quindi un
 * interruttore idratato male si porterebbe dietro il negozio intero.
 */
class ShopSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShopSettingsSeeder::class);
    }

    #[Test]
    public function la_pagina_apre_con_lo_shop_acceso(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ShopSettingsPage::class)
            ->assertSuccessful()
            ->assertSet('data.shop.enabled', true)
            ->assertSet('data.auctions.enabled', true);
    }

    #[Test]
    public function accendere_le_aste_non_spegne_lo_shop(): void
    {
        SiteSetting::set('auctions.enabled', '0');

        Livewire::actingAs($this->superAdmin())
            ->test(ShopSettingsPage::class)
            ->set('data.auctions.enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(
            filter_var(SiteSetting::get('shop.enabled'), FILTER_VALIDATE_BOOLEAN),
            'Lo shop si è spento salvando le impostazioni delle aste.',
        );
        $this->assertTrue(filter_var(SiteSetting::get('auctions.enabled'), FILTER_VALIDATE_BOOLEAN));

        $this->get(route('shop'))->assertOk()->assertInertia(
            fn ($page) => $page->component('Public/Shop/Index'),
        );
    }

    #[Test]
    public function una_chiave_che_non_esiste_si_apre_con_il_suo_valore_di_partenza(): void
    {
        // È il caso della produzione: le righe `shop.*` non erano mai state
        // create. Il sito leggeva i valori predefiniti e funzionava, ma il
        // modulo si apriva vuoto e il primo Salva scriveva lo shop spento.
        SiteSetting::query()->where('key', 'like', 'shop.%')->delete();
        SiteSetting::clearCache();

        Livewire::actingAs($this->superAdmin())
            ->test(ShopSettingsPage::class)
            ->assertSet('data.shop.enabled', true)
            ->set('data.auctions.enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(
            filter_var(SiteSetting::get('shop.enabled'), FILTER_VALIDATE_BOOLEAN),
            'Salvare le impostazioni ha spento uno shop che nessuno aveva spento.',
        );
        $this->assertSame('10', (string) SiteSetting::get('shop.max_qty_per_product'));

        // La soglia della spedizione gratuita è l'eccezione: vuota significa
        // "vale quella della zona di spedizione", e il modulo non deve
        // proporre un numero che il checkout poi non applica.
        $this->assertEmpty(SiteSetting::get('shop.free_shipping_threshold'));

        $this->get(route('shop'))->assertOk()->assertInertia(
            fn ($page) => $page->component('Public/Shop/Index'),
        );
    }

    #[Test]
    public function salvare_senza_modifiche_lascia_le_impostazioni_come_sono(): void
    {
        $prima = SiteSetting::getGroup('shop');

        Livewire::actingAs($this->superAdmin())
            ->test(ShopSettingsPage::class)
            ->call('save')
            ->assertHasNoErrors();

        SiteSetting::clearCache();

        $this->assertSame($prima['max_qty_per_product'], SiteSetting::getGroup('shop')['max_qty_per_product']);
        $this->assertTrue(filter_var(SiteSetting::get('shop.enabled'), FILTER_VALIDATE_BOOLEAN));
    }

    #[Test]
    public function spegnere_lo_shop_manda_in_manutenzione(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ShopSettingsPage::class)
            ->set('data.shop.enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->get(route('shop'))->assertOk()->assertInertia(
            fn ($page) => $page->component('Public/Shop/Maintenance'),
        );
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user->refresh();
    }
}
