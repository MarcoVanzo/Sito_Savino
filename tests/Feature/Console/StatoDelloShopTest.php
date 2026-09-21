<?php

namespace Tests\Feature\Console;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `shop:stato` apre e chiude il negozio da riga di comando, senza passare dal
 * pannello: serve quando il negozio è chiuso per sbaglio e nessuno ha le
 * credenziali sotto mano (21/09/2026).
 */
class StatoDelloShopTest extends TestCase
{
    use RefreshDatabase;

    private function stato(): ?string
    {
        return SiteSetting::where('key', 'shop.enabled')->value('value');
    }

    public function test_senza_argomento_dice_soltanto_come_sta(): void
    {
        SiteSetting::set('shop.enabled', '0');

        $this->artisan('shop:stato')
            ->expectsOutputToContain('chiuso')
            ->assertSuccessful();

        // E non scrive niente.
        $this->assertSame('0', $this->stato());
    }

    public function test_apre_il_negozio(): void
    {
        SiteSetting::set('shop.enabled', '0');

        $this->artisan('shop:stato', ['stato' => 'aperto'])
            ->expectsOutput('Negozio aperto.')
            ->assertSuccessful();

        $this->assertSame('1', $this->stato());
    }

    public function test_chiude_il_negozio(): void
    {
        SiteSetting::set('shop.enabled', '1');

        $this->artisan('shop:stato', ['stato' => 'chiuso'])
            ->expectsOutput('Negozio chiuso.')
            ->assertSuccessful();

        $this->assertSame('0', $this->stato());
    }

    public function test_non_riscrive_uno_stato_che_e_gia_quello(): void
    {
        SiteSetting::set('shop.enabled', '1');
        $prima = SiteSetting::where('key', 'shop.enabled')->value('updated_at');

        $this->travel(2)->minutes();

        $this->artisan('shop:stato', ['stato' => 'apri'])
            ->expectsOutputToContain('era già aperto')
            ->assertSuccessful();

        $this->assertEquals($prima, SiteSetting::where('key', 'shop.enabled')->value('updated_at'));
    }

    public function test_uno_stato_che_non_esiste_non_tocca_niente(): void
    {
        SiteSetting::set('shop.enabled', '1');

        $this->artisan('shop:stato', ['stato' => 'forse'])
            ->expectsOutputToContain('non riconosciuto')
            ->assertFailed();

        $this->assertSame('1', $this->stato());
    }

    public function test_la_pagina_dello_shop_riapre_davvero(): void
    {
        SiteSetting::set('shop.enabled', '0');

        $this->get(route('shop'))->assertInertia(fn ($page) => $page->component('Public/Shop/Maintenance'));

        $this->artisan('shop:stato', ['stato' => 'aperto'])->assertSuccessful();

        $this->get(route('shop'))->assertInertia(fn ($page) => $page->component('Public/Shop/Index'));
    }
}
