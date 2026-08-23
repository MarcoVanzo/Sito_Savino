<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\NewsletterAnalyticsPage;
use App\Filament\Pages\SocialAnalyticsPage;
use App\Filament\Pages\WebAnalyticsPage;
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
