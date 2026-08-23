<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\NewsletterAnalyticsPage;
use App\Filament\Pages\SocialAnalyticsPage;
use App\Filament\Pages\WebAnalyticsPage;
use App\Filament\Widgets\Analytics\NewsletterKpiWidget;
use App\Filament\Widgets\Analytics\NewsletterRatesWidget;
use App\Filament\Widgets\Analytics\NewsletterTrendWidget;
use App\Filament\Widgets\Analytics\NewsletterVolumeWidget;
use App\Filament\Widgets\Analytics\SocialKpiWidget;
use App\Filament\Widgets\Analytics\SocialTrendWidget;
use App\Filament\Widgets\Analytics\WebKpiWidget;
use App\Filament\Widgets\Analytics\WebTrendWidget;
use App\Models\AnalyticsSite;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il selettore "Periodo" delle tre pagine di analytics.
 *
 * Le pagine passavano già il periodo con getWidgetData(), il metodo giusto, e i
 * widget lo dichiaravano come proprietà pubblica: al primo caricamento tutto
 * arrivava. Mancava #[Reactive], e senza quello Livewire applica i mount param
 * una sola volta — cambiare periodo aggiornava la pagina ma lasciava i widget
 * fermi ai 28 giorni con cui erano stati montati.
 *
 * È un guasto invisibile: i numeri restano a schermo, semplicemente non sono
 * quelli del periodo scelto. Per questo si verifica il meccanismo e non
 * l'aspetto — i widget sono lazy e il loro contenuto non compare nell'HTML
 * della pagina, mentre lo snapshot Livewire dei figli sì.
 */
class AnalyticsPeriodoWidgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, array<int, string>, int}>
     */
    public static function pagine(): array
    {
        return [
            // pagina, proprietà che devono viaggiare fino ai widget, quanti widget
            'social' => [SocialAnalyticsPage::class, ['accountId', 'days'], 2],
            'sito' => [WebAnalyticsPage::class, ['siteId', 'days'], 2],
            'newsletter' => [NewsletterAnalyticsPage::class, ['days'], 4],
        ];
    }

    /**
     * @param  class-string  $pagina
     * @param  array<int, string>  $attese
     */
    #[Test]
    #[DataProvider('pagine')]
    public function il_periodo_arriva_ai_widget_ed_e_reattivo(string $pagina, array $attese, int $quanti): void
    {
        Http::fake();
        $this->preparaAccount();
        $this->actingAs($this->gestioneComunicazione());

        // Il periodo si passa al mount: nella risposta a un aggiornamento
        // successivo Livewire non ri-serializza i figli.
        $widget = $this->widgetFigli(
            Livewire::test($pagina, ['days' => 7])->assertSuccessful()->html()
        );

        $this->assertCount($quanti, $widget);

        foreach ($widget as $nome => $snapshot) {
            $this->assertSame(7, $snapshot['dati']['days'] ?? null, "Il widget {$nome} non ha ricevuto il periodo.");

            foreach ($attese as $prop) {
                $this->assertContains(
                    $prop,
                    $snapshot['props'],
                    "La proprietà {$prop} di {$nome} non è reattiva: il widget resterebbe fermo al valore iniziale.",
                );
            }
        }
    }

    #[Test]
    #[DataProvider('pagine')]
    public function la_pagina_espone_il_periodo_col_metodo_che_filament_chiama(string $pagina, array $attese, int $quanti): void
    {
        Http::fake();
        $this->preparaAccount();
        $this->actingAs($this->gestioneComunicazione());

        $test = Livewire::test($pagina)->assertSuccessful();
        $test->set('days', 90);

        // getWidgetData() è il nome che Filament\Pages\Page dichiara e che
        // components/page/index.blade.php invoca a ogni render.
        $this->assertSame(90, $test->instance()->getWidgetData()['days'] ?? null);
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function widget(): array
    {
        return [
            'social kpi' => [SocialKpiWidget::class, 'accountId'],
            'social trend' => [SocialTrendWidget::class, 'accountId'],
            'sito kpi' => [WebKpiWidget::class, 'siteId'],
            'sito trend' => [WebTrendWidget::class, 'siteId'],
            'newsletter kpi' => [NewsletterKpiWidget::class, ''],
            'newsletter tassi' => [NewsletterRatesWidget::class, ''],
            'newsletter volumi' => [NewsletterVolumeWidget::class, ''],
            'newsletter andamento' => [NewsletterTrendWidget::class, ''],
        ];
    }

    /**
     * Ogni widget montato per conto suo, con il servizio esterno che non
     * risponde. È il requisito dichiarato per queste tre pagine: un widget che
     * va in errore perché Google o Meta sono lenti è peggio di uno che dice che
     * il dato non c'è. Qui si esegue davvero il corpo del widget, cosa che
     * montare la pagina non fa — i widget sono lazy.
     *
     * @param  class-string  $widget
     */
    #[Test]
    #[DataProvider('widget')]
    public function il_widget_regge_il_servizio_esterno_che_non_risponde(string $widget, string $chiave): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $this->actingAs($this->gestioneComunicazione());

        Livewire::test($widget, $this->parametri($chiave, 7))->assertSuccessful();
    }

    /**
     * Lo stesso widget con due periodi diversi: verifica che il corpo giri per
     * entrambi e non solo per il default.
     *
     * @param  class-string  $widget
     */
    #[Test]
    #[DataProvider('widget')]
    public function il_widget_si_monta_con_qualunque_periodo(string $widget, string $chiave): void
    {
        Http::fake();
        $this->actingAs($this->gestioneComunicazione());

        // L'entità si crea una volta sola: property_id e ig_account_id hanno un
        // vincolo di unicità, rifarla a ogni giro fa fallire l'inserimento.
        $parametri = $this->parametri($chiave, 7);

        foreach ([7, 90] as $giorni) {
            $test = Livewire::test($widget, [...$parametri, 'days' => $giorni])->assertSuccessful();

            $this->assertSame($giorni, $test->get('days'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parametri(string $chiave, int $giorni): array
    {
        $parametri = ['days' => $giorni];

        if ($chiave === 'accountId') {
            $parametri['accountId'] = SocialAccount::factory()->create()->id;
        }

        if ($chiave === 'siteId') {
            $parametri['siteId'] = AnalyticsSite::factory()->create(['property_id' => '123456789'])->id;
        }

        return $parametri;
    }

    /**
     * @return array<string, array{dati: array<string, mixed>, props: array<int, string>}>
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
                'dati' => $snapshot['data'] ?? [],
                'props' => $snapshot['memo']['props'] ?? [],
            ];
        }

        return $figli;
    }

    private function preparaAccount(): void
    {
        AnalyticsSite::factory()->create(['name' => 'Sito ufficiale', 'property_id' => '123456789']);
        SocialAccount::factory()->create();
    }

    /**
     * `role` non è mass-assignable: passarlo alla factory non avrebbe effetto.
     */
    private function gestioneComunicazione(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::CommunicationManager, 'must_change_password' => false])->save();

        return $user->refresh();
    }
}
