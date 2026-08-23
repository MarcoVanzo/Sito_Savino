<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\ShopAnalyticsPage;
use App\Filament\Widgets\CustomersWidget;
use App\Filament\Widgets\ShopKpiWidget;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il selettore "Periodo" della pagina Analytics Shop.
 *
 * È un guasto che non si vede a schermo: i widget mostrano comunque dei numeri,
 * solo sempre quelli degli ultimi 30 giorni. Erano due rotture in fila — la
 * pagina esponeva i dati con un metodo che Filament non chiama mai
 * (`getHeaderWidgetsData()` invece di `getWidgetData()`) e i widget li leggevano
 * da `$this->filterFormData`, proprietà inesistente che in Livewire torna null
 * senza sollevare nulla.
 *
 * Da qui l'ordine dei test: prima il gesto dell'utente, poi il collegamento fra
 * pagina e widget (l'anello che si era spezzato), poi l'effetto sui numeri.
 */
class ShopAnalyticsPeriodTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function scegliere_un_periodo_dal_menu_lo_imposta_sulla_pagina(): void
    {
        $this->actingAs($this->responsabileShop());

        Livewire::test(ShopAnalyticsPage::class)
            ->assertSuccessful()
            ->callAction('period', ['period' => '90'])
            ->assertHasNoActionErrors()
            // la Select rende stringhe: senza cast la proprietà tipizzata
            // riceverebbe '90' e il confronto con le opzioni fallirebbe.
            ->assertSet('period', 90);
    }

    /**
     * L'anello che si era spezzato. Filament passa i dati di getWidgetData() come
     * mount param dei widget figli, che nello snapshot Livewire della pagina sono
     * ispezionabili uno per uno: è l'unico modo di vedere il collegamento, perché
     * i widget sono lazy e il loro contenuto non compare nell'HTML della pagina.
     */
    #[Test]
    public function il_periodo_arriva_a_tutti_e_sette_i_widget_della_pagina(): void
    {
        $this->actingAs($this->responsabileShop());

        // Il periodo va passato al mount: nella risposta a un aggiornamento
        // successivo Livewire non ri-serializza i figli, e non ci sarebbe niente
        // da leggere.
        $page = Livewire::test(ShopAnalyticsPage::class, ['period' => 90])->assertSuccessful();

        $widget = $this->widgetFigli($page->html());

        $this->assertCount(7, $widget, 'La pagina dichiara sette widget di testata.');

        foreach ($widget as $nome => $snapshot) {
            $this->assertSame(90, $snapshot['period'], "Il widget {$nome} non ha ricevuto il periodo.");

            // Senza #[Reactive] il valore iniziale arriverebbe lo stesso, ma
            // Livewire non lo elencherebbe fra le props: il widget resterebbe
            // fermo al periodo con cui è stato montato.
            $this->assertContains('period', $snapshot['props'], "Il periodo di {$nome} non è reattivo.");
        }
    }

    #[Test]
    public function il_periodo_scelto_cambia_i_numeri_dei_kpi(): void
    {
        $this->actingAs($this->responsabileShop());

        $this->ordinePagato(giorniFa: 3, totale: 100);
        $this->ordinePagato(giorniFa: 45, totale: 250);

        // 7 giorni: solo l'ordine recente, quindi fatturato e valore medio coincidono.
        Livewire::test(ShopKpiWidget::class, ['period' => 7])
            ->assertSuccessful()
            ->assertSee('€100,00')
            ->assertSee('7 giorni')
            ->assertDontSee('€350,00');

        // 90 giorni: entrambi gli ordini, e il valore medio si dimezza.
        Livewire::test(ShopKpiWidget::class, ['period' => 90])
            ->assertSuccessful()
            ->assertSee('€350,00')
            ->assertSee('€175,00')
            ->assertSee('90 giorni');
    }

    #[Test]
    public function il_periodo_scelto_cambia_il_conteggio_clienti(): void
    {
        $this->actingAs($this->responsabileShop());

        $this->ordinePagato(giorniFa: 3, totale: 100);
        $this->ordinePagato(giorniFa: 45, totale: 250);

        Livewire::test(CustomersWidget::class, ['period' => 7])
            ->assertSuccessful()
            ->assertSee('1 registrati, 0 guest');

        Livewire::test(CustomersWidget::class, ['period' => 90])
            ->assertSuccessful()
            ->assertSee('2 registrati, 0 guest');
    }

    /**
     * `$period` è pubblica, quindi il client può scriverla senza passare dal menu.
     * Finisce in `subDays()` e, su SalesTrendWidget, in un CarbonPeriod percorso
     * giorno per giorno: con 50.000 la singola richiesta costa una quindicina di
     * MB e più di un mega di HTML. Fuori dalle opzioni si torna a 30.
     */
    #[Test]
    public function un_periodo_fuori_dalle_opzioni_non_arriva_ai_widget(): void
    {
        $this->actingAs($this->responsabileShop());

        foreach ([50000, -5, 0, 31] as $fuoriScala) {
            $page = Livewire::test(ShopAnalyticsPage::class);
            $page->set('period', $fuoriScala);

            $this->assertSame(
                ['period' => 30],
                $page->instance()->getWidgetData(),
                "Il periodo {$fuoriScala} non è stato ricondotto al default.",
            );
        }
    }

    #[Test]
    public function i_periodi_del_menu_passano_invece_tutti(): void
    {
        $this->actingAs($this->responsabileShop());

        foreach ([7, 30, 90, 365] as $ammesso) {
            $page = Livewire::test(ShopAnalyticsPage::class);
            $page->set('period', $ammesso);

            // Il nome del metodo non è libero: è quello che Filament\Pages\Page
            // dichiara e che components/page/index.blade.php invoca a ogni render.
            $this->assertSame(['period' => $ammesso], $page->instance()->getWidgetData());
        }
    }

    /**
     * Il default resta 30 giorni per chi monta il widget senza parametri.
     */
    #[Test]
    public function senza_parametri_il_widget_usa_trenta_giorni(): void
    {
        $this->actingAs($this->responsabileShop());

        Livewire::test(ShopKpiWidget::class)
            ->assertSuccessful()
            ->assertSee('30 giorni');
    }

    /**
     * Estrae, dallo snapshot Livewire della pagina, il periodo e l'elenco delle
     * props reattive di ogni widget figlio.
     *
     * @return array<string, array{period: mixed, props: array<int, string>}>
     */
    private function widgetFigli(string $html): array
    {
        preg_match_all('/wire:snapshot="([^"]*)"/', $html, $match);

        $figli = [];

        foreach ($match[1] as $grezzo) {
            $snapshot = json_decode(html_entity_decode($grezzo, ENT_QUOTES), true);
            $nome = $snapshot['memo']['name'] ?? '';

            if (! str_contains($nome, 'widget')) {
                continue;
            }

            $figli[$nome] = [
                'period' => $snapshot['data']['period'] ?? null,
                'props' => $snapshot['memo']['props'] ?? [],
            ];
        }

        return $figli;
    }

    private function ordinePagato(int $giorniFa, float $totale): Order
    {
        $order = Order::factory()->paid()->create();

        $order->forceFill([
            'total_price' => $totale,
            'created_at' => now()->subDays($giorniFa),
        ])->save();

        return $order;
    }

    /**
     * `role` non è mass-assignable: passarlo alla factory non avrebbe effetto.
     */
    private function responsabileShop(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::ShopManager, 'must_change_password' => false])->save();

        return $user->refresh();
    }
}
