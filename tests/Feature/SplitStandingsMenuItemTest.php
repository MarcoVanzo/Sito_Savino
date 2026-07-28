<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La voce di menu vive nel database, quindi la separazione fra Risultati e
 * Classifica è una migrazione dati e non codice: questi test la esercitano su
 * un menu ricostruito a mano, perché sotto RefreshDatabase la migrazione trova
 * le tabelle vuote ed esce senza fare nulla.
 */
class SplitStandingsMenuItemTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_07_28_170000_split_standings_menu_item.php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Il database di test è condiviso e conserva i dati di un seed
        // precedente: senza svuotare il menu la migrazione agirebbe sulle voci
        // di quello, non su quelle create qui. Siamo dentro la transazione di
        // RefreshDatabase, quindi la cancellazione non esce dal test.
        MenuItem::query()->delete();
    }

    private function seedMenu(): MenuItem
    {
        $parent = MenuItem::create([
            'label' => ['it' => 'Stagione', 'en' => 'Season'],
            'url' => '/stagione/',
            'location' => 'main',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        MenuItem::create([
            'label' => ['it' => 'Classifica e Risultati'],
            'url' => '/stagione/risultati/',
            'parent_id' => $parent->id,
            'location' => 'main',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        MenuItem::create([
            'label' => ['it' => 'CEV Champions League'],
            'url' => '/stagione/cev/',
            'parent_id' => $parent->id,
            'location' => 'main',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        return $parent;
    }

    #[Test]
    public function separa_la_voce_in_risultati_e_classifica(): void
    {
        $parent = $this->seedMenu();

        $this->migration()->up();

        $voices = MenuItem::where('parent_id', $parent->id)->orderBy('sort_order')->get();

        $this->assertSame(
            ['Risultati', 'Classifica', 'CEV Champions League'],
            $voices->map(fn (MenuItem $item) => $item->getTranslation('label', 'it', false))->all(),
        );

        $standings = $voices->firstWhere('url', '/stagione/classifica/');
        $this->assertNotNull($standings);
        $this->assertSame('Standings', $standings->getTranslation('label', 'en', false));

        // Le voci successive scalano invece di sovrapporsi alla classifica.
        $this->assertSame(4, $voices->firstWhere('url', '/stagione/cev/')->sort_order);
    }

    #[Test]
    public function rieseguirla_non_duplica_la_voce(): void
    {
        $this->seedMenu();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, MenuItem::where('url', '/stagione/classifica/')->count());
    }

    #[Test]
    public function il_rollback_ripristina_la_voce_unica(): void
    {
        $this->seedMenu();

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(0, MenuItem::where('url', '/stagione/classifica/')->count());
        $this->assertSame(
            'Classifica e Risultati',
            MenuItem::firstWhere('url', '/stagione/risultati/')->getTranslation('label', 'it', false),
        );
    }

    #[Test]
    public function un_menu_senza_la_voce_attesa_resta_intatto(): void
    {
        // Se il menu è stato personalizzato non si indovina dove inserire la
        // nuova voce: meglio non toccare nulla che sporcare la navigazione.
        MenuItem::create([
            'label' => ['it' => 'Altro'],
            'url' => '/altro/',
            'location' => 'main',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->migration()->up();

        $this->assertSame(1, MenuItem::count());
        $this->assertSame(0, MenuItem::where('url', '/stagione/classifica/')->count());
    }
}
