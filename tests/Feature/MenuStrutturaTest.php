<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'albero del menu deve arrivare al browser come elenco, non come oggetto.
 *
 * `reject()` conserva le chiavi della collezione: scartando la voce di mezzo —
 * succede con una pagina messa in bozza, o con un documento non ancora
 * caricato — la numerazione resta con un buco e in JSON diventa
 * `{"0": …, "2": …}` invece di `[…]`. Nel browser `navigation.map(...)` andava
 * in errore dentro PublicLayout e la pagina restava bianca: non solo il menu,
 * tutta la pagina.
 */
class MenuStrutturaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function voce(string $etichetta, string $url, int $posizione): MenuItem
    {
        return MenuItem::create([
            'label' => ['it' => $etichetta, 'en' => $etichetta],
            'url' => $url,
            'location' => 'main',
            'sort_order' => $posizione,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function scartare_una_voce_di_mezzo_non_rompe_l_elenco(): void
    {
        Page::factory()->create(['slug' => 'pagina-in-bozza', 'status' => 'draft']);

        $this->voce('Prima', '/sponsor', 1);
        $this->voce('In bozza', '/pagina-in-bozza', 2);
        $this->voce('Terza', '/shop', 3);

        $albero = MenuItem::getTree('main');
        $etichette = array_column($albero, 'label');

        $this->assertNotContains('In bozza', $etichette);
        $this->assertIsList($albero);
        $this->assertSame('[', substr(json_encode($albero), 0, 1), 'Il menu arriva al browser come oggetto invece che come elenco.');
    }

    #[Test]
    public function scartare_una_voce_figlia_non_rompe_il_sottoelenco(): void
    {
        Page::factory()->create(['slug' => 'figlia-in-bozza', 'status' => 'draft']);

        $genitore = $this->voce('Sezione', '/sponsor', 1);

        foreach ([['Una', '/shop'], ['Bozza', '/figlia-in-bozza'], ['Tre', '/gallery']] as $posizione => [$etichetta, $url]) {
            MenuItem::create([
                'label' => ['it' => $etichetta, 'en' => $etichetta],
                'url' => $url,
                'location' => 'main',
                'parent_id' => $genitore->id,
                'sort_order' => $posizione,
                'is_active' => true,
            ]);
        }

        $sezione = collect(MenuItem::getTree('main'))->firstWhere('label', 'Sezione');
        $figlie = $sezione['children'];

        $this->assertNotContains('Bozza', array_column($figlie, 'label'));
        $this->assertIsList($figlie);
    }

    /** La prova che conta: quello che il browser riceve davvero. */
    #[Test]
    public function la_home_riceve_un_elenco(): void
    {
        Page::factory()->create(['slug' => 'pagina-in-bozza', 'status' => 'draft']);

        $this->voce('Prima', '/sponsor', 1);
        $this->voce('In bozza', '/pagina-in-bozza', 2);
        $this->voce('Terza', '/shop', 3);

        $this->withoutVite();
        $risposta = $this->get('/');

        $navigazione = $risposta->viewData('page')['props']['navigation'];

        $this->assertIsList($navigazione, 'Il menu passato alla pagina non e\' un elenco.');
    }
}
