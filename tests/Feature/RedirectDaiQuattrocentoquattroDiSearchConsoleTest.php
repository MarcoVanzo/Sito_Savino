<?php

namespace Tests\Feature;

use App\Models\Reindirizzamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I redirect nati dai 404 di Search Console del 14/10/2026: la migrazione li
 * carica tutti, e il sito risponde come deciso.
 */
class RedirectDaiQuattrocentoquattroDiSearchConsoleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_migrazione_carica_tutte_le_righe_del_csv(): void
    {
        $righe = count(file(database_path('data/redirect_search_console_2026_10_14.csv'), FILE_SKIP_EMPTY_LINES)) - 1;

        $this->assertSame($righe, Reindirizzamento::query()->count());
    }

    #[Test]
    public function i_vecchi_indirizzi_portano_alla_destinazione_decisa(): void
    {
        $this->get('/sitemap_index.xml')->assertRedirect('/sitemap-index.xml')->assertStatus(301);
        $this->get('/talent-day-2025/')->assertRedirect('/youth/talent-day');
        $this->get('/wp-content/uploads/2024/08/2.-LINEE-GUIDA-Safeguarding-pubblicato-def.pdf')->assertRedirect('/societa/safeguarding');
        $this->get('/embed/')->assertStatus(410);
    }
}
