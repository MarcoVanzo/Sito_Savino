<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Database\Seeders\CorporateGovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il seeder della colonna Corporate Governance gira a ogni avvio del container.
 *
 * La versione precedente si fermava se trovava il titolo con indirizzo `#`:
 * corretto quell'indirizzo, al riavvio successivo ha ricreato l'intera colonna
 * una seconda volta, con dentro i link a pagine che non esistono. Nel footer
 * comparivano due volte Safeguarding e i quattro protocolli.
 */
class CorporateGovernanceSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function semina(): void
    {
        (new CorporateGovernanceSeeder)->run();
    }

    /**
     * @return Collection<int, MenuItem>
     */
    private function colonne()
    {
        return MenuItem::where('location', 'footer')
            ->whereNull('parent_id')
            ->get()
            ->filter(fn (MenuItem $voce) => $voce->getTranslation('label', 'it', false) === 'Corporate Governance')
            ->values();
    }

    #[Test]
    public function crea_la_colonna_con_i_suoi_documenti(): void
    {
        $this->semina();

        $colonna = $this->colonne()->first();

        $this->assertNotNull($colonna);
        $this->assertSame(5, $colonna->children()->count());
        $this->assertSame(
            'documento:modello_organizzativo',
            $colonna->children()->get()->first(fn ($f) => $f->getTranslation('label', 'it', false) === 'Modello Organizzativo')?->url
        );
    }

    /** Il difetto vero: girare due volte non deve raddoppiare niente. */
    #[Test]
    public function rieseguirlo_non_duplica_la_colonna(): void
    {
        $this->semina();
        $this->semina();
        $this->semina();

        $this->assertCount(1, $this->colonne());
        $this->assertSame(5, $this->colonne()->first()->children()->count());
    }

    /** Nemmeno dopo che qualcuno ha cambiato l'indirizzo del titolo. */
    #[Test]
    public function non_duplica_se_il_titolo_cambia_indirizzo(): void
    {
        $this->semina();
        $this->colonne()->first()->update(['url' => '/qualcosa/altro']);

        $this->semina();

        $this->assertCount(1, $this->colonne());
    }

    /** Trovando due colonne, le riunisce in una sola. */
    #[Test]
    public function riunisce_le_colonne_gia_duplicate(): void
    {
        $this->semina();

        $doppione = MenuItem::create([
            'label' => ['it' => 'Corporate Governance', 'en' => 'Corporate Governance'],
            'url' => '#',
            'location' => 'footer',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        MenuItem::create([
            'label' => ['it' => 'Protocollo Razzismo', 'en' => 'Protocollo Razzismo'],
            'url' => '/protocollo-razzismo',
            'location' => 'footer',
            'parent_id' => $doppione->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->semina();

        $this->assertCount(1, $this->colonne());
        $this->assertDatabaseMissing('menu_items', ['id' => $doppione->id]);
        $this->assertDatabaseMissing('menu_items', ['url' => '/protocollo-razzismo']);
    }

    /** Il titolo non deve restare un "#": non apre nulla. */
    #[Test]
    public function il_titolo_porta_alla_pagina_safeguarding(): void
    {
        $this->semina();

        $this->assertSame('/societa/safeguarding', $this->colonne()->first()->url);
    }
}
