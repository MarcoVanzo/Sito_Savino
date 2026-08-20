<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GalleryEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Copre le migrazioni che rendono traducibili i nomi delle categorie news e i
 * titoli degli album di gallery.
 *
 * In produzione queste colonne contengono testo semplice scritto quando i due
 * model non erano traducibili: è quel passaggio a dover funzionare, e non lo si
 * esercita con un `migrate:fresh` (le migrazioni girano su tabelle vuote e i
 * seeder arrivano dopo). Qui le righe legacy vengono ricreate a mano.
 */
class TranslatableCategoriesMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le migrazioni sono classi anonime: `up()` esiste solo sull'istanza
     * restituita dal file, non su Migration.
     */
    private function migrazione(string $file): object
    {
        return require database_path("migrations/{$file}.php");
    }

    #[Test]
    public function il_testo_semplice_diventa_json_per_lingua(): void
    {
        DB::table('categories')->insert([
            'name' => 'Notizie',
            'slug' => 'notizie-legacy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migrazione('2026_08_05_100100_make_categories_and_gallery_events_translatable')->up();

        $categoria = Category::where('slug', 'notizie-legacy')->firstOrFail();

        $this->assertSame('Notizie', $categoria->getTranslation('name', 'it'));
        $this->assertSame(['it' => 'Notizie'], $categoria->getTranslations('name'));
    }

    #[Test]
    public function una_riga_gia_convertita_non_viene_incapsulata_due_volte(): void
    {
        DB::table('categories')->insert([
            'name' => json_encode(['it' => 'Società', 'en' => 'Club'], JSON_UNESCAPED_UNICODE),
            'slug' => 'societa-gia-tradotta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Rieseguirla è previsto: le migrazioni girano a ogni deploy.
        $migrazione = $this->migrazione('2026_08_05_100100_make_categories_and_gallery_events_translatable');
        $migrazione->up();
        $migrazione->up();

        $categoria = Category::where('slug', 'societa-gia-tradotta')->firstOrFail();

        $this->assertSame(['it' => 'Società', 'en' => 'Club'], $categoria->getTranslations('name'));
    }

    #[Test]
    public function i_titoli_degli_album_generati_vengono_tradotti(): void
    {
        DB::table('gallery_events')->insert([
            'title' => 'Giugno 2026 — News',
            'event_date' => '2026-06-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migrazione('2026_08_05_100100_make_categories_and_gallery_events_translatable')->up();
        $this->migrazione('2026_08_05_100200_translate_gallery_event_titles_to_english')->up();

        $album = GalleryEvent::where('event_date', '2026-06-01')->firstOrFail();

        $this->assertSame('Giugno 2026 — News', $album->getTranslation('title', 'it'));
        $this->assertSame('June 2026 — News', $album->getTranslation('title', 'en'));
    }

    #[Test]
    public function un_titolo_scritto_in_redazione_resta_intatto(): void
    {
        DB::table('gallery_events')->insert([
            'title' => 'Festa promozione Serie B1',
            'event_date' => '2026-05-20',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migrazione('2026_08_05_100100_make_categories_and_gallery_events_translatable')->up();
        $this->migrazione('2026_08_05_100200_translate_gallery_event_titles_to_english')->up();

        $album = GalleryEvent::where('event_date', '2026-05-20')->firstOrFail();

        // Non segue lo schema "<Mese> <anno> — News": nessuna traduzione inventata.
        $this->assertSame(['it' => 'Festa promozione Serie B1'], $album->getTranslations('title'));
    }
}
