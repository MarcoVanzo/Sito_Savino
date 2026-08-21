<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le voci del menu devono portare da qualche parte.
 *
 * Due difetti segnalati dalla redazione: mettendo l'indirizzo del sito della
 * Savino Del Bene Spa o del canale YouTube la voce smetteva di funzionare, e
 * mettendo una pagina in bozza la voce restava lì a portare a "pagina non
 * trovata".
 *
 * Le migrazioni popolano già menu e pagine: i test qui aggiungono le proprie
 * voci e cercano quelle, invece di pretendere un menu vuoto.
 */
class MenuLinksTest extends TestCase
{
    use RefreshDatabase;

    private const ETICHETTA = 'Voce Di Prova';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function voce(array $attributi = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'label' => ['it' => self::ETICHETTA, 'en' => self::ETICHETTA],
            'url' => '/stagione',
            'location' => 'main',
            'is_active' => true,
            'sort_order' => 999,
        ], $attributi));
    }

    private function pagina(string $slug, PostStatus $stato): Page
    {
        return Page::updateOrCreate(
            ['slug' => $slug],
            ['title' => ['it' => $slug], 'template' => 'Public/ContentPage', 'status' => $stato],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vocePropria(): ?array
    {
        foreach (MenuItem::getTree('main') as $voce) {
            if (($voce['label'] ?? null) === self::ETICHETTA) {
                return $voce;
            }
        }

        return null;
    }

    /** Un indirizzo esterno arriva al frontend così com'è, senza prefisso di lingua. */
    #[Test]
    public function un_indirizzo_esterno_resta_intatto(): void
    {
        $this->voce(['url' => 'https://www.savinodelbene.com/it/home/']);

        $this->assertSame('https://www.savinodelbene.com/it/home/', $this->vocePropria()['href'] ?? null);
    }

    #[Test]
    public function una_voce_che_porta_a_una_pagina_in_bozza_non_compare(): void
    {
        $this->pagina('prova-bozza', PostStatus::Draft);
        $this->voce(['url' => '/sociale/prova-bozza/']);

        $this->assertNull($this->vocePropria());
    }

    #[Test]
    public function una_voce_che_porta_a_una_pagina_pubblicata_resta(): void
    {
        $this->pagina('prova-pubblicata', PostStatus::Published);
        $this->voce(['url' => '/sociale/prova-pubblicata/']);

        $this->assertNotNull($this->vocePropria());
    }

    /** Anche una sottovoce sparisce, non solo la voce di primo livello. */
    #[Test]
    public function anche_una_sottovoce_in_bozza_sparisce(): void
    {
        $this->pagina('prova-figlia-bozza', PostStatus::Draft);

        $padre = $this->voce(['url' => '/stagione']);
        $this->voce([
            'label' => ['it' => 'Figlia'],
            'url' => '/sociale/prova-figlia-bozza',
            'parent_id' => $padre->id,
        ]);

        $this->assertSame([], $this->vocePropria()['children'] ?? null);
    }

    /** Un indirizzo esterno non ha una pagina dietro: non va mai nascosto. */
    #[Test]
    public function un_indirizzo_esterno_non_viene_mai_nascosto(): void
    {
        $this->pagina('home', PostStatus::Draft);
        $this->voce(['url' => 'https://www.youtube.com/@savinodelbenevolley']);

        $this->assertNotNull($this->vocePropria());
    }

    /**
     * Mettere una pagina in bozza deve togliere la voce subito, non alla
     * scadenza della cache del menu.
     */
    #[Test]
    public function mettere_in_bozza_aggiorna_il_menu_senza_aspettare(): void
    {
        $pagina = $this->pagina('prova-cache', PostStatus::Published);
        $this->voce(['url' => '/sociale/prova-cache/']);

        $this->assertNotNull($this->vocePropria());

        $pagina->update(['status' => PostStatus::Draft]);

        $this->assertNull($this->vocePropria());
    }
}
