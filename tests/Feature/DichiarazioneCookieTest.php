<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Support\DichiarazioneCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'elenco dei cookie pubblicato nella Cookie Policy.
 *
 * Arriva dalla scansione settimanale, non dall'editor: è il pezzo che tiene la
 * pagina vera senza che nessuno se ne ricordi.
 */
class DichiarazioneCookieTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La pagina esiste già: la crea il seeder, come in produzione. Qui si
     * verifica solo che sia pubblicata e con il template generico.
     */
    private function paginaCookiePolicy(): Page
    {
        $pagina = Page::firstOrNew(['slug' => 'cookie-policy']);

        $pagina->fill([
            'title' => 'Cookie Policy',
            'status' => PostStatus::Published,
            'template' => 'Public/ContentPage',
        ])->save();

        return $pagina;
    }

    public function test_la_cookie_policy_riceve_i_cookie_trovati_dalla_scansione(): void
    {
        $this->paginaCookiePolicy();

        $this->get('/cookie-policy')->assertInertia(function ($pagina) {
            $pagina->component('Public/ContentPage')
                ->has('dichiarazioneCookie.categorie')
                ->where('dichiarazioneCookie.aggiornata_il', fn ($valore) => is_string($valore) && $valore !== '');

            $categorie = collect($pagina->toArray()['props']['dichiarazioneCookie']['categorie']);

            // I cookie di sessione ci sono sempre: se mancassero, la scansione
            // non avrebbe caricato niente e la pagina direbbe il falso.
            $necessari = $categorie->firstWhere('chiave', 'necessari');
            $this->assertNotNull($necessari, 'manca la categoria dei cookie necessari');
            $this->assertNotEmpty($necessari['cookie']);
        });
    }

    public function test_le_altre_pagine_di_solo_testo_non_la_ricevono(): void
    {
        $pagina = Page::firstOrNew(['slug' => 'privacy-policy']);

        $pagina->fill([
            'title' => 'Privacy Policy',
            'status' => PostStatus::Published,
            'template' => 'Public/ContentPage',
        ])->save();

        $this->get('/privacy-policy')
            ->assertInertia(fn ($risposta) => $risposta->component('Public/ContentPage')->missing('dichiarazioneCookie'));
    }

    public function test_ogni_cookie_dichiarato_ha_una_categoria_conosciuta(): void
    {
        $dichiarazione = DichiarazioneCookie::perIlFrontend('it');

        foreach ($dichiarazione['categorie'] as $categoria) {
            $this->assertContains($categoria['chiave'], ['necessari', 'statistiche', 'marketing', 'non classificati']);

            foreach ($categoria['cookie'] as $cookie) {
                $this->assertNotSame('', $cookie['nome']);

                // Un cookie che sappiamo nominare deve anche essere spiegato:
                // un elenco senza scopi non informa nessuno.
                if ($categoria['chiave'] !== 'non classificati') {
                    $this->assertNotNull($cookie['scopo'], $cookie['nome'].' è classificato ma non spiegato');
                    $this->assertNotNull($cookie['fornitore'], $cookie['nome'].' è classificato ma senza fornitore');
                }
            }
        }
    }

    public function test_la_spiegazione_segue_la_lingua(): void
    {
        $italiano = DichiarazioneCookie::perIlFrontend('it');
        $inglese = DichiarazioneCookie::perIlFrontend('en');

        $scopoIt = $italiano['categorie'][0]['cookie'][0]['scopo'] ?? null;
        $scopoEn = $inglese['categorie'][0]['cookie'][0]['scopo'] ?? null;

        $this->assertNotNull($scopoIt);
        $this->assertNotNull($scopoEn);
        $this->assertNotSame($scopoIt, $scopoEn);
    }

    public function test_senza_il_file_della_scansione_la_pagina_non_si_rompe(): void
    {
        $file = database_path('data/cookie_rilevati.json');
        $contenuto = file_get_contents($file);

        try {
            unlink($file);

            $this->paginaCookiePolicy();

            $this->get('/cookie-policy')->assertSuccessful()
                ->assertInertia(fn ($pagina) => $pagina->where('dichiarazioneCookie.categorie', []));
        } finally {
            file_put_contents($file, $contenuto);
        }
    }
}
