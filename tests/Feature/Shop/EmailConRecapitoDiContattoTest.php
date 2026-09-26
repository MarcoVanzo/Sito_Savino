<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Mail\OrderCancelled;
use App\Mail\OrderConfirmation;
use App\Mail\OrderStatusChanged;
use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le email leggevano `shop.contact_email`, chiave tolta dalla migrazione
 * `clean_up_obsolete_site_settings`: il piè di pagina restava senza indirizzo
 * e annullamenti e rimborsi invitavano a scrivere al mittente. Il recapito è
 * quello generale del sito, `contact.email`.
 */
class EmailConRecapitoDiContattoTest extends TestCase
{
    use RefreshDatabase;

    private const RECAPITO = 'recapito-di-prova@savinodelbenevolley.it';

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::updateOrCreate(['key' => 'email', 'group' => 'contact'], [
            'value' => self::RECAPITO,
            'type' => 'email',
        ]);
        Cache::flush();
    }

    #[Test]
    public function il_pie_di_pagina_porta_il_recapito_di_contatto(): void
    {
        $order = Order::factory()->create(['locale' => 'it']);

        (new OrderConfirmation($order))->assertSeeInHtml('mailto:'.self::RECAPITO, false);
    }

    #[Test]
    public function l_annullamento_invita_a_scrivere_al_recapito_di_contatto(): void
    {
        $order = Order::factory()->create(['locale' => 'it']);

        (new OrderCancelled($order))->assertSeeInHtml(self::RECAPITO);
    }

    #[Test]
    public function il_rimborso_invita_a_scrivere_al_recapito_di_contatto(): void
    {
        $order = Order::factory()->create(['locale' => 'it', 'status' => OrderStatus::Refunded]);

        $html = (new OrderStatusChanged($order))->render();

        $this->assertSame(2, substr_count($html, 'mailto:'.self::RECAPITO), 'nota del rimborso e piè di pagina');
    }
}
