<?php

namespace Tests\Feature;

use Database\Seeders\MenuItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le `description` delle voci di sottomenu finiscono sotto gli occhi del
 * pubblico nel mega-menu: qui si verifica che la migrazione sostituisca i
 * percorsi e gli appunti redazionali lasciati dal seeder, che non tocchi ciò
 * che la redazione ha già scritto e che sia rieseguibile.
 */
class CleanMenuItemDescriptionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testo che non deve comparire come descrizione: un percorso, o un appunto
     * di lavorazione con la freccia/trattino iniziale.
     */
    private const DIRTY_PATTERN = '/^(\/|->|- )/';

    private function runMigration(): void
    {
        (require database_path('migrations/2026_08_05_100000_clean_menu_item_descriptions.php'))->up();
    }

    private function insertItem(string $url, ?string $description, string $location = 'main', ?int $parentId = null): int
    {
        return DB::table('menu_items')->insertGetId([
            'label' => json_encode(['it' => 'Voce']),
            'url' => $url,
            'description' => $description,
            'parent_id' => $parentId ?? $this->parentId($location),
            'location' => $location,
            'sort_order' => 0,
            'is_active' => true,
            'is_highlight' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function parentId(string $location): int
    {
        return DB::table('menu_items')->insertGetId([
            'label' => json_encode(['it' => 'Sezione']),
            'url' => '/sezione',
            'location' => $location,
            'sort_order' => 0,
            'is_active' => true,
            'is_highlight' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function descriptionOf(int $id): ?string
    {
        return DB::table('menu_items')->where('id', $id)->value('description');
    }

    #[Test]
    public function sostituisce_il_percorso_usato_come_descrizione(): void
    {
        $id = $this->insertItem('/stagione/risultati/', json_encode(['it' => '/stagione/risultati/']));

        $this->runMigration();

        $description = json_decode((string) $this->descriptionOf($id), true);
        $this->assertSame('Calendario e tabellini', $description['it']);
        $this->assertSame('Fixtures and box scores', $description['en']);
    }

    #[Test]
    public function sostituisce_l_appunto_redazionale_in_entrambe_le_lingue(): void
    {
        $id = $this->insertItem('/sociale/aste/', json_encode(['it' => '-> E-Shop', 'en' => '-> E-Shop']));

        $this->runMigration();

        $description = json_decode((string) $this->descriptionOf($id), true);
        $this->assertSame("Aste sull'e-shop ufficiale", $description['it']);
        $this->assertSame('Auctions on the official e-shop', $description['en']);
    }

    #[Test]
    public function azzera_la_descrizione_senza_testo(): void
    {
        $id = $this->insertItem('/gallery', json_encode(['it' => null]), 'footer');

        $this->runMigration();

        $this->assertNull($this->descriptionOf($id));
    }

    #[Test]
    public function non_tocca_una_descrizione_gia_scritta_dalla_redazione(): void
    {
        $id = $this->insertItem('/stagione/risultati/', json_encode(['it' => 'Tutte le gare di campionato']));

        $this->runMigration();

        $description = json_decode((string) $this->descriptionOf($id), true);
        $this->assertSame('Tutte le gare di campionato', $description['it']);
        $this->assertArrayNotHasKey('en', $description);
    }

    #[Test]
    public function e_rieseguibile_senza_effetti(): void
    {
        $id = $this->insertItem('/comunicazione/double-face/', json_encode(['it' => '-> YouTube Channel']));

        $this->runMigration();
        $first = $this->descriptionOf($id);
        $this->runMigration();

        $this->assertSame($first, $this->descriptionOf($id));
    }

    #[Test]
    public function il_seeder_non_produce_piu_descrizioni_sporche(): void
    {
        $this->seed(MenuItemSeeder::class);

        foreach (DB::table('menu_items')->get(['id', 'url', 'description']) as $row) {
            if ($row->description === null) {
                continue;
            }

            $values = json_decode((string) $row->description, true) ?: ['it' => $row->description];

            foreach ($values as $locale => $value) {
                $this->assertIsString(
                    $value,
                    "La voce {$row->url} ha una descrizione [$locale] senza testo: usare NULL."
                );
                $this->assertDoesNotMatchRegularExpression(
                    self::DIRTY_PATTERN,
                    $value,
                    "La voce {$row->url} ha una descrizione [$locale] non pubblicabile: \"$value\"."
                );
            }
        }
    }
}
