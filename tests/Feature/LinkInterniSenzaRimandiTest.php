<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Support\SeguiILinkInterni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Menu, footer e home puntano all'indirizzo finale, non a un rimando.
 *
 * Il menu del test e' quello seminato dalle migrazioni, compresa quella del
 * 14/10/2026 che riscrive i vecchi indirizzi: una voce nuova che punta a
 * `/ticketing` o `/youth/u17` fa fallire il primo test. Le pagine del CMS qui
 * non ci sono tutte, quindi i 404 non si guardano: li segnala `link:verifica`
 * sui dati veri.
 */
class LinkInterniSenzaRimandiTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRAZIONE = 'database/migrations/2026_10_14_100000_i_link_interni_puntano_all_indirizzo_finale.php';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * @param  list<array{origine: string, voce: string, href: string}>  $link
     * @return list<string>
     */
    private function rimandi(array $link): array
    {
        return collect(SeguiILinkInterni::problemi($link))
            ->where('problema', 'rimando')
            ->map(fn (array $p) => "{$p['origine']} «{$p['voce']}» {$p['href']} → {$p['stato']} {$p['destinazione']}")
            ->values()
            ->all();
    }

    #[Test]
    public function il_menu_seminato_non_punta_a_rimandi(): void
    {
        $link = SeguiILinkInterni::delMenu();

        $this->assertNotEmpty($link);
        $this->assertSame([], $this->rimandi($link));
    }

    #[Test]
    public function la_home_non_ha_link_interni_a_rimandi(): void
    {
        $link = [...SeguiILinkInterni::dellaPagina('/'), ...SeguiILinkInterni::dellaPagina('/en')];

        $this->assertContains('/stagione/risultati', array_column($link, 'href'), 'I link delle props non sono stati raccolti.');
        $this->assertSame([], $this->rimandi($link));
    }

    #[Test]
    public function una_voce_che_punta_a_un_rimando_viene_segnalata_con_la_destinazione(): void
    {
        MenuItem::create([
            'label' => ['it' => 'Vecchia biglietteria', 'en' => 'Old box office'],
            'url' => '/ticketing/',
            'location' => 'footer',
            'is_active' => true,
            'sort_order' => 999,
        ]);

        $rimandi = collect(SeguiILinkInterni::problemi(SeguiILinkInterni::delMenu()))->where('problema', 'rimando');

        $this->assertSame(['/ticketing', '/en/ticketing'], $rimandi->pluck('href')->all());
        $this->assertSame(301, $rimandi->first()['stato']);
        $this->assertSame('/ticketing/biglietteria', $rimandi->first()['destinazione']);

        $this->artisan('link:verifica', ['--pagina' => ['/']])
            ->expectsOutputToContain('/ticketing/biglietteria')
            ->assertFailed();
    }

    #[Test]
    public function la_migrazione_riscrive_solo_i_vecchi_indirizzi(): void
    {
        $vecchia = MenuItem::create(['label' => ['it' => 'A'], 'url' => '/youth/u17/', 'location' => 'main', 'is_active' => true]);
        $conQuery = MenuItem::create(['label' => ['it' => 'B'], 'url' => '/risultati?squadra=savino', 'location' => 'main', 'is_active' => true]);
        $scelta = MenuItem::create(['label' => ['it' => 'C'], 'url' => '/ticketing/abbonamenti', 'location' => 'main', 'is_active' => true]);
        $esterna = MenuItem::create(['label' => ['it' => 'D'], 'url' => 'https://example.com/ticketing', 'location' => 'main', 'is_active' => true]);
        DB::table('site_settings')->updateOrInsert(['key' => 'cta_ticketing_url'], ['value' => '/ticketing', 'group' => 'home', 'type' => 'url']);

        (require base_path(self::MIGRAZIONE))->up();

        $this->assertSame('/stagione/u17', $vecchia->fresh()->url);
        $this->assertSame('/stagione/risultati?squadra=savino', $conQuery->fresh()->url);
        $this->assertSame('/ticketing/abbonamenti', $scelta->fresh()->url);
        $this->assertSame('https://example.com/ticketing', $esterna->fresh()->url);
        $this->assertSame('/ticketing/biglietteria', DB::table('site_settings')->where('key', 'cta_ticketing_url')->value('value'));
    }

    /** La sottolineatura della sezione aperta non dipende dalla pagina a cui punta la voce. */
    #[Test]
    public function la_voce_di_primo_livello_porta_la_sua_sezione(): void
    {
        MenuItem::create(['label' => ['it' => 'Prova'], 'url' => '/societa/storia', 'location' => 'main', 'is_active' => true, 'sort_order' => 999]);

        $voce = collect(MenuItem::getTree('main'))->firstWhere('label', 'Prova');
        app()->setLocale('en');
        Cache::flush();
        $inglese = collect(MenuItem::getTree('main'))->firstWhere('href', '/en/societa/storia');

        $this->assertSame('/societa', $voce['sezione']);
        $this->assertSame('/en/societa', $inglese['sezione'] ?? null);
    }
}
