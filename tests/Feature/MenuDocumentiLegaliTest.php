<?php

namespace Tests\Feature;

use App\Console\Commands\VerificaIlMenu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le voci di Corporate Governance devono aprire i PDF caricati dal pannello.
 *
 * L'abbinamento lo faceva il footer confrontando l'etichetta italiana
 * ("Protocollo Bullismo"): sul sito inglese nessuna etichetta corrispondeva e
 * tutti e quattro i link portavano alla pagina Safeguarding. Ora la voce
 * dichiara la chiave del documento e la risoluzione avviene nel backend.
 */
class MenuDocumentiLegaliTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function voceDocumento(string $chiave, string $etichettaIt, string $etichettaEn): MenuItem
    {
        return MenuItem::create([
            'label' => ['it' => $etichettaIt, 'en' => $etichettaEn],
            'url' => 'documento:'.$chiave,
            'location' => 'footer',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    #[Test]
    public function il_link_e_quello_del_documento_caricato(): void
    {
        SiteSetting::set('legal.protocollo_bullismo', 'https://esempio.test/bullismo.pdf');
        Cache::flush();

        $this->assertSame(
            'https://esempio.test/bullismo.pdf',
            MenuItem::href('documento:protocollo_bullismo', 'it')
        );
    }

    /**
     * In archivio c'e' il percorso sul disco, non l'indirizzo. In produzione i
     * file stanno su Spaces: "legal/Protocollo.pdf" nel footer diventava un
     * percorso relativo alla pagina aperta e non apriva niente.
     */
    #[Test]
    public function il_percorso_sul_disco_diventa_un_indirizzo_completo(): void
    {
        SiteSetting::set('legal.modello_organizzativo', 'legal/Modello-Organizzativo.pdf');
        Cache::flush();

        $indirizzo = MenuItem::href('documento:modello_organizzativo', 'it');

        $this->assertNotSame('legal/Modello-Organizzativo.pdf', $indirizzo);
        $this->assertStringContainsString('Modello-Organizzativo.pdf', $indirizzo);
        $this->assertTrue(
            str_starts_with($indirizzo, 'http') || str_starts_with($indirizzo, '/'),
            "Il link del documento e' relativo alla pagina aperta: {$indirizzo}"
        );
    }

    /** Il difetto vero: in inglese l'abbinamento per etichetta non funzionava. */
    #[Test]
    public function funziona_anche_sul_sito_inglese(): void
    {
        SiteSetting::set('legal.codice_tutela_minori', 'https://esempio.test/codice.pdf');
        $this->voceDocumento('codice_tutela_minori', 'Codice Tutela Minori', 'Child Protection Code');
        Cache::flush();

        app()->setLocale('en');
        $albero = MenuItem::getTree('footer');

        $voce = collect($albero)->firstWhere('label', 'Child Protection Code');

        $this->assertNotNull($voce);
        $this->assertSame('https://esempio.test/codice.pdf', $voce['href']);
    }

    /** Meglio nessuna voce che una voce che non apre nulla. */
    #[Test]
    public function la_voce_sparisce_finche_il_documento_non_c_e(): void
    {
        $this->voceDocumento('modello_organizzativo', 'Modello Organizzativo', 'Organisational Model');
        Cache::flush();

        $etichette = collect(MenuItem::getTree('footer'))->pluck('label');

        $this->assertNotContains('Modello Organizzativo', $etichette);
    }

    /** Sostituire il file dal pannello deve cambiare il link, non domani. */
    #[Test]
    public function sostituire_il_documento_aggiorna_subito_il_menu(): void
    {
        SiteSetting::set('legal.protocollo_razzismo', 'https://esempio.test/vecchio.pdf');
        $this->voceDocumento('protocollo_razzismo', 'Protocollo Razzismo', 'Anti-Racism Protocol');
        Cache::flush();

        $questaVoce = fn () => collect(MenuItem::getTree('footer'))
            ->firstWhere('label', 'Protocollo Razzismo')['href'] ?? null;

        $this->assertSame('https://esempio.test/vecchio.pdf', $questaVoce());

        SiteSetting::set('legal.protocollo_razzismo', 'https://esempio.test/nuovo.pdf');

        $this->assertSame('https://esempio.test/nuovo.pdf', $questaVoce());
    }

    /**
     * Il controllo che avrebbe intercettato i difetti segnalati dalla
     * redazione. Sono i tre casi trovati sui dati veri.
     */
    #[Test]
    public function il_comando_riconosce_le_voci_rotte(): void
    {
        $comando = new VerificaIlMenu;

        $this->assertNotNull($comando->motivoDelGuasto('#'));
        $this->assertNotNull($comando->motivoDelGuasto('comunicazione/double-face/'));
        $this->assertNotNull($comando->motivoDelGuasto('/pagina-che-non-esiste-affatto'));
        $this->assertNotNull($comando->motivoDelGuasto(''));

        $this->assertNull($comando->motivoDelGuasto('/sponsor'));
        $this->assertNull($comando->motivoDelGuasto('https://www.savinodelbene.com/it/home/'));
    }

    /** Una voce che porta a una pagina in bozza e' un "pagina non trovata". */
    #[Test]
    public function il_comando_segnala_le_pagine_in_bozza(): void
    {
        Page::factory()->create(['slug' => 'pagina-in-bozza', 'status' => 'draft']);

        $this->assertSame(
            'la pagina è in bozza',
            (new VerificaIlMenu)->motivoDelGuasto('/pagina-in-bozza')
        );
    }
}
