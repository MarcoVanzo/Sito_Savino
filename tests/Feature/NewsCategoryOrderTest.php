<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'ordine delle categorie nel filtro delle news lo decide la redazione.
 *
 * Comparivano in ordine alfabetico, e "Challenge Cup" finiva prima della
 * stagione in corso. Ora c'è una posizione, modificabile dal pannello: chi non
 * ce l'ha va in fondo, non in cima.
 */
class NewsCategoryOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function categoriaConNews(string $nome, string $slug, int $posizione): Category
    {
        $categoria = Category::create(['name' => ['it' => $nome], 'slug' => $slug, 'sort_order' => $posizione]);

        Post::factory()->create(['status' => 'publish', 'published_at' => now()->subDay()])
            ->categories()->attach($categoria->id);

        return $categoria;
    }

    /**
     * @return list<string>
     */
    private function ordineInPagina(): array
    {
        $nomi = [];

        $this->get('/news')->assertInertia(function (AssertableInertia $pagina) use (&$nomi) {
            foreach ($pagina->toArray()['props']['categories'] ?? [] as $categoria) {
                $nomi[] = is_array($categoria['name']) ? ($categoria['name']['it'] ?? '') : $categoria['name'];
            }
        });

        return $nomi;
    }

    #[Test]
    public function le_categorie_seguono_la_posizione_scelta(): void
    {
        // Nomi scelti apposta in ordine alfabetico inverso rispetto alla
        // posizione: se l'ordinamento sbagliasse, uscirebbero al contrario.
        $this->categoriaConNews('Zeta', 'zeta', 1);
        $this->categoriaConNews('Alfa', 'alfa', 2);
        $this->categoriaConNews('Mike', 'mike', 3);

        $this->assertSame(['Zeta', 'Alfa', 'Mike'], $this->ordineInPagina());
    }

    #[Test]
    public function chi_non_ha_posizione_finisce_in_fondo(): void
    {
        $this->categoriaConNews('Senza posizione', 'senza', 0);
        $this->categoriaConNews('Prima', 'prima', 1);
        $this->categoriaConNews('Seconda', 'seconda', 2);

        $this->assertSame(['Prima', 'Seconda', 'Senza posizione'], $this->ordineInPagina());
    }

    #[Test]
    public function a_parita_di_posizione_resta_l_ordine_alfabetico(): void
    {
        $this->categoriaConNews('Beta', 'beta', 5);
        $this->categoriaConNews('Alfa', 'alfa', 5);

        $this->assertSame(['Alfa', 'Beta'], $this->ordineInPagina());
    }
}
