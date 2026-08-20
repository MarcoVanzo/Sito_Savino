<?php

namespace Tests\Feature\Shop;

use App\Mail\OrderCancelled;
use App\Mail\OrderConfirmation;
use App\Mail\OrderShipped;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le mail transazionali sono in coda: quando partono, la locale della richiesta
 * che ha creato l'ordine non c'è più. La lingua viene quindi congelata
 * sull'ordine e riletta dal Mailable.
 */
class OrderEmailLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_ordine_registra_la_lingua_della_richiesta(): void
    {
        app()->setLocale('en');
        $order = Order::factory()->create();

        $this->assertSame('en', $order->fresh()->locale);
    }

    public function test_una_locale_non_supportata_ricade_sull_italiano(): void
    {
        app()->setLocale('de');
        $order = Order::factory()->create();

        $this->assertSame('it', $order->fresh()->locale);
    }

    public function test_la_conferma_ordine_e_in_inglese_per_un_ordine_inglese(): void
    {
        app()->setLocale('it');
        $order = Order::factory()->create(['locale' => 'en']);

        $mailable = new OrderConfirmation($order);

        $mailable->assertHasSubject("Order Confirmation #{$order->order_number}");
        $mailable->assertSeeInHtml('Thank you for your order!');
        $mailable->assertDontSeeInHtml('Grazie per il tuo ordine!');
    }

    public function test_la_conferma_ordine_resta_in_italiano_per_un_ordine_italiano(): void
    {
        app()->setLocale('en');
        $order = Order::factory()->create(['locale' => 'it']);

        $mailable = new OrderConfirmation($order);

        $mailable->assertHasSubject("Conferma Ordine #{$order->order_number}");
        $mailable->assertSeeInHtml('Grazie per il tuo ordine!');
    }

    public function test_spedizione_e_annullamento_seguono_la_lingua_dell_ordine(): void
    {
        $order = Order::factory()->create(['locale' => 'en']);

        (new OrderShipped($order))->assertSeeInHtml('Your order is on its way!');
        (new OrderCancelled($order))->assertSeeInHtml('Order cancelled');
    }

    public function test_l_utente_registra_la_lingua_di_registrazione(): void
    {
        app()->setLocale('en');

        $this->assertSame('en', User::factory()->create()->fresh()->locale);
    }
}
