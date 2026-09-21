<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sponsor, Contatti e Gallery hanno una rotta propria e non passano dal
 * controller delle pagine del CMS: si portavano al frontend il record grezzo.
 *
 * Il risultato era che su quelle tre pagine non giravano ne' il ripiego sulla
 * lingua di partenza ne' la riscrittura dei percorsi dei file caricati dal
 * pannello: la pagina Sponsor inglese mostrava le etichette dei numeri
 * d'impatto senza i numeri, che in italiano ci sono.
 */
class PaginePubblicheConRottaPropriaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $it
     * @param  array<string, mixed>  $en
     */
    private function pagina(string $slug, string $template, array $it, array $en): Page
    {
        // Le pagine di sezione sono gia' in archivio (il seeder dei test le
        // crea): qui si riscrivono i contenuti, non se ne aggiunge una seconda
        // con lo stesso slug.
        return Page::updateOrCreate(['slug' => $slug], [
            'title' => ['it' => ucfirst($slug), 'en' => ucfirst($slug)],
            'template' => $template,
            'status' => PostStatus::Published,
            'content_data' => ['it' => $it, 'en' => $en],
        ]);
    }

    #[Test]
    public function la_pagina_sponsor_inglese_prende_dall_italiano_i_numeri_d_impatto(): void
    {
        $this->pagina('sponsor', 'Public/Sponsor', [
            'stat1_label' => 'Followers Social',
            'stat1_value' => '1.6M+',
            'contact_email' => 'marketing@savinodelbenevolley.it',
        ], [
            'stat1_label' => 'Social Followers',
        ]);

        $this->get('/en/sponsor')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                // Il numero non ha lingua: senza ripiego la casella spariva.
                ->where('page.content_data.stat1_value', '1.6M+')
                ->where('page.content_data.contact_email', 'marketing@savinodelbenevolley.it')
                // L'etichetta invece e' un testo, e resta quella inglese.
                ->where('page.content_data.stat1_label', 'Social Followers'));
    }

    #[Test]
    public function la_pagina_sponsor_italiana_resta_quella_che_era(): void
    {
        $this->pagina('sponsor', 'Public/Sponsor', [
            'stat1_label' => 'Followers Social',
            'stat1_value' => '1.6M+',
        ], [
            'stat1_label' => 'Social Followers',
        ]);

        $this->get('/sponsor')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('page.content_data.stat1_label', 'Followers Social')
                ->where('page.content_data.stat1_value', '1.6M+'));
    }

    #[Test]
    public function la_pagina_contatti_inglese_prende_dall_italiano_i_valori_senza_lingua(): void
    {
        $this->pagina('contatti', 'Public/Contatti', [
            'form_title' => 'Scrivici',
            'maps_iframe_src' => 'https://www.google.com/maps/embed?pb=palazzetto',
        ], [
            'form_title' => 'Write to Us',
        ]);

        $this->get('/en/contacts')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('page.content_data.maps_iframe_src', 'https://www.google.com/maps/embed?pb=palazzetto')
                ->where('page.content_data.form_title', 'Write to Us'));
    }

    /**
     * I campi di upload salvano il percorso relativo al disco: in produzione i
     * file stanno su Spaces e un "/storage/…" composto nel template non porta
     * da nessuna parte.
     */
    #[Test]
    public function il_file_caricato_dal_pannello_diventa_un_indirizzo_pubblico(): void
    {
        Storage::fake();

        $pagina = $this->pagina('sponsor', 'Public/Sponsor', [
            'feature_image' => 'pagine/locandina.jpg',
        ], []);

        $dati = $pagina->datiPerIlFrontend();

        $this->assertSame(Storage::url('pagine/locandina.jpg'), $dati['content_data']['feature_image']);
    }
}
