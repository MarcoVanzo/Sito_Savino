<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La verifica sui dati veri aveva trovato la pagina Sponsor senza la copia
 * inglese — `/en/sponsor` mostrava "Diventa Partner" — e `title-sponsor` con
 * `content_data` ridotto alla stringa `{"en": ""}`. In tutti e due i casi il
 * sito cadeva sui testi di ripiego cablati nei componenti: online sembrava a
 * posto, nel pannello i campi erano vuoti.
 */
class RiempieIContenutiDiPaginaMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function eseguiLaMigrazione(): void
    {
        (require database_path('migrations/2026_08_22_110000_riempie_i_contenuti_di_pagina_rimasti_vuoti.php'))->up();
    }

    /**
     * Le pagine del CMS le creano già le migrazioni: qui si porta quella dello
     * slug allo stato da cui deve partire la prova, senza duplicarla.
     */
    private function creaPagina(string $slug, string $template, ?string $contentData, ?string $metaDescription = null): int
    {
        DB::table('pages')->updateOrInsert(['slug' => $slug], [
            'title' => json_encode(['it' => $slug, 'en' => $slug]),
            'template' => $template,
            'content_data' => $contentData,
            'meta_description' => $metaDescription,
            'status' => 'publish',
            'updated_at' => now(),
        ]);

        return (int) DB::table('pages')->where('slug', $slug)->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function contentData(int $id): array
    {
        return json_decode((string) DB::table('pages')->where('id', $id)->value('content_data'), true) ?: [];
    }

    #[Test]
    public function riempie_la_lingua_che_manca(): void
    {
        $id = $this->creaPagina('sponsor', 'Public/Sponsor', json_encode([
            'it' => ['cta_title' => 'Diventa Partner'],
        ]));

        $this->eseguiLaMigrazione();

        $dati = $this->contentData($id);

        $this->assertSame('Become a Partner', $dati['en']['cta_title']);
        $this->assertSame('Our Partners', $dati['en']['hero_subtitle']);
    }

    #[Test]
    public function non_sovrascrive_quello_che_ha_scritto_la_redazione(): void
    {
        $id = $this->creaPagina('sponsor', 'Public/Sponsor', json_encode([
            'it' => ['cta_title' => 'Sostieni la squadra'],
            'en' => ['cta_title' => 'Support the team'],
        ]));

        $this->eseguiLaMigrazione();

        $dati = $this->contentData($id);

        $this->assertSame('Sostieni la squadra', $dati['it']['cta_title']);
        $this->assertSame('Support the team', $dati['en']['cta_title']);
    }

    #[Test]
    public function ripara_il_content_data_ridotto_a_una_stringa(): void
    {
        // È lo stato in cui il pannello aveva lasciato `title-sponsor`: una
        // stringa dove il resto del codice si aspetta un array per lingua.
        $id = $this->creaPagina('title-sponsor', 'Public/Sponsor', json_encode(['en' => '']));

        $this->eseguiLaMigrazione();

        $dati = $this->contentData($id);

        $this->assertIsArray($dati['it']);
        $this->assertIsArray($dati['en']);
        $this->assertSame('Diventa Partner', $dati['it']['cta_title']);
        $this->assertSame('Become a Partner', $dati['en']['cta_title']);
    }

    #[Test]
    public function ripara_la_stringa_anche_senza_valori_iniziali(): void
    {
        // `ContentPage` non ha valori iniziali: se il confronto guardasse i
        // dati già normalizzati non ci sarebbe niente da scrivere e lo scalare
        // resterebbe in tabella.
        $id = $this->creaPagina('hospitality', 'Public/ContentPage', json_encode(['en' => '']));

        $this->eseguiLaMigrazione();

        $dati = $this->contentData($id);

        $this->assertSame([], $dati['en']);
        $this->assertSame([], $dati['it']);
    }

    #[Test]
    public function assegna_la_descrizione_seo_alle_pagine_che_non_ce_l_hanno(): void
    {
        $id = $this->creaPagina('cookie-policy', 'Public/ContentPage', null);

        $this->eseguiLaMigrazione();

        $descrizione = json_decode((string) DB::table('pages')->where('id', $id)->value('meta_description'), true);

        $this->assertStringContainsString('cookie', mb_strtolower((string) $descrizione['it']));
        $this->assertStringContainsString('cookie', mb_strtolower((string) $descrizione['en']));
    }

    #[Test]
    public function lascia_stare_la_descrizione_gia_scritta(): void
    {
        $mia = json_encode(['it' => 'La mia descrizione', 'en' => 'My description']);
        $id = $this->creaPagina('shop', 'Public/Shop', null, $mia);

        $this->eseguiLaMigrazione();

        $this->assertSame($mia, DB::table('pages')->where('id', $id)->value('meta_description'));
    }
}
