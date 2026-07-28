<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il menu vive nel database, quindi completare le traduzioni inglesi è una
 * migrazione dati e non codice: questi test la esercitano su un menu ricostruito
 * a mano, perché sotto RefreshDatabase la migrazione trova le tabelle vuote ed
 * esce senza fare nulla.
 */
class TranslateMenuItemsToEnglishTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_07_28_180000_translate_menu_items_to_english.php');
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

    /**
     * Un frammento del menu reale: una colonna con motto, una voce figlia con
     * descrizione, e un omonimo nel footer.
     */
    private function seedMenu(): void
    {
        $season = MenuItem::create([
            'label' => ['it' => 'Stagione'],
            'url' => '/stagione/',
            'location' => 'main',
            'sort_order' => 0,
            'is_active' => true,
            'motto_title' => ['it' => 'La Squadra'],
            'motto_subtitle' => ['it' => 'Roster, staff e risultati della stagione in corso'],
        ]);

        MenuItem::create([
            'label' => ['it' => 'Settore Giovanile'],
            'url' => '/youth/settore-giovanile/',
            'parent_id' => $season->id,
            'location' => 'main',
            'sort_order' => 0,
            'is_active' => true,
            'description' => ['it' => 'Foto squadre di base'],
        ]);

        $footer = MenuItem::create([
            'label' => ['it' => 'Stagione'],
            'url' => '/stagione',
            'location' => 'footer',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // Stesso URL della colonna che la contiene: la migrazione deve
        // distinguerle dal livello, non dall'URL.
        MenuItem::create([
            'label' => ['it' => 'Roster A1'],
            'url' => '/stagione',
            'parent_id' => $footer->id,
            'location' => 'footer',
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function itemAt(string $location, string $url, bool $root): MenuItem
    {
        $item = MenuItem::where('location', $location)
            ->where('url', $url)
            ->when($root, fn ($query) => $query->whereNull('parent_id'))
            ->when(! $root, fn ($query) => $query->whereNotNull('parent_id'))
            ->first();

        $this->assertNotNull($item, "Voce non trovata: {$location} {$url}");

        return $item;
    }

    #[Test]
    public function aggiunge_le_traduzioni_inglesi_mancanti(): void
    {
        $this->seedMenu();

        $this->migration()->up();

        $season = $this->itemAt('main', '/stagione/', root: true);
        $this->assertSame('Season', $season->getTranslation('label', 'en', false));
        $this->assertSame('The Team', $season->getTranslation('motto_title', 'en', false));
        $this->assertSame(
            'Roster, staff and results from the current season',
            $season->getTranslation('motto_subtitle', 'en', false),
        );

        $youth = $this->itemAt('main', '/youth/settore-giovanile/', root: false);
        $this->assertSame('Youth Programme', $youth->getTranslation('label', 'en', false));
        $this->assertSame('Grassroots team photos', $youth->getTranslation('description', 'en', false));
    }

    #[Test]
    public function conserva_litaliano_esistente(): void
    {
        $this->seedMenu();

        $this->migration()->up();

        $season = $this->itemAt('main', '/stagione/', root: true);
        $this->assertSame('Stagione', $season->getTranslation('label', 'it', false));
        $this->assertSame('La Squadra', $season->getTranslation('motto_title', 'it', false));
    }

    #[Test]
    public function distingue_le_voci_con_lo_stesso_url_per_posizione_e_livello(): void
    {
        $this->seedMenu();

        $this->migration()->up();

        // Tre voci con URL /stagione(/): colonna main, colonna footer e prima
        // voce del footer. Ognuna ha la sua traduzione.
        $this->assertSame('Season', $this->itemAt('main', '/stagione/', root: true)->getTranslation('label', 'en', false));
        $this->assertSame('Season', $this->itemAt('footer', '/stagione', root: true)->getTranslation('label', 'en', false));
        $this->assertSame('A1 Roster', $this->itemAt('footer', '/stagione', root: false)->getTranslation('label', 'en', false));
    }

    #[Test]
    public function non_sovrascrive_una_traduzione_gia_presente(): void
    {
        $this->seedMenu();

        $season = $this->itemAt('main', '/stagione/', root: true);
        $season->setTranslation('label', 'en', 'The 2026/27 Season');
        $season->setTranslation('motto_title', 'en', 'Our Squad');
        $season->save();

        $this->migration()->up();

        $season->refresh();
        $this->assertSame('The 2026/27 Season', $season->getTranslation('label', 'en', false));
        $this->assertSame('Our Squad', $season->getTranslation('motto_title', 'en', false));

        // Le colonne ancora scoperte della stessa voce vengono comunque
        // completate: il rispetto del lavoro della redazione è per campo.
        $this->assertSame(
            'Roster, staff and results from the current season',
            $season->getTranslation('motto_subtitle', 'en', false),
        );
    }

    #[Test]
    public function rieseguirla_non_cambia_nulla(): void
    {
        $this->seedMenu();

        $this->migration()->up();
        $primoGiro = MenuItem::orderBy('id')->get(['label', 'description', 'motto_title', 'motto_subtitle'])->toJson();

        $this->migration()->up();
        $secondoGiro = MenuItem::orderBy('id')->get(['label', 'description', 'motto_title', 'motto_subtitle'])->toJson();

        $this->assertSame($primoGiro, $secondoGiro);
    }

    #[Test]
    public function non_tocca_una_voce_rinominata_dalla_redazione(): void
    {
        $this->seedMenu();

        $renamed = $this->itemAt('main', '/youth/settore-giovanile/', root: false);
        $renamed->setTranslation('label', 'it', 'Vivaio Savino');
        $renamed->save();

        $this->migration()->up();

        $renamed->refresh();
        // L'italiano non è più quello atteso: scriverci sopra "Youth Programme"
        // vorrebbe dire tradurre una voce diversa da quella conosciuta.
        $this->assertSame('', $renamed->getTranslation('label', 'en', false));
        // La descrizione, rimasta quella nota, viene invece tradotta.
        $this->assertSame('Grassroots team photos', $renamed->getTranslation('description', 'en', false));
    }

    #[Test]
    public function ignora_le_voci_sconosciute(): void
    {
        MenuItem::create([
            'label' => ['it' => 'Fan Village'],
            'url' => '/fan-village/',
            'location' => 'main',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->migration()->up();

        $this->assertSame(
            '',
            MenuItem::firstWhere('url', '/fan-village/')->getTranslation('label', 'en', false),
        );
    }

    #[Test]
    public function il_rollback_rimuove_solo_le_traduzioni_di_questa_migrazione(): void
    {
        $this->seedMenu();

        $footerRoot = $this->itemAt('footer', '/stagione', root: true);
        $footerRoot->setTranslation('label', 'en', 'Current Season');
        $footerRoot->save();

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame('', $this->itemAt('main', '/stagione/', root: true)->getTranslation('label', 'en', false));
        // Ritocco della redazione: sopravvive al rollback.
        $this->assertSame('Current Season', $this->itemAt('footer', '/stagione', root: true)->getTranslation('label', 'en', false));
    }
}
