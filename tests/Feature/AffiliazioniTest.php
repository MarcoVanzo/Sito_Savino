<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Progetto Affiliazioni: le societa' del progetto con i loro loghi, divise nei
 * tre livelli del sito precedente e con il collegamento al sito di ognuna.
 */
class AffiliazioniTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_pagina_di_contenuto_diventa_affiliazioni_senza_perdere_il_testo(): void
    {
        $pagina = Page::create([
            'title' => ['it' => 'Progetto Affiliazioni', 'en' => 'Affiliation Project'],
            'slug' => 'affiliazioni',
            'template' => 'Public/ContentPage',
            'status' => PostStatus::Published,
            'content' => ['it' => '<p>Un progetto per crescere insieme</p>'],
            'content_data' => ['it' => [], 'en' => []],
        ]);

        $migrazione = require database_path('migrations/2026_09_18_090000_pagina_affiliazioni_con_i_loghi_delle_societa.php');
        $migrazione->up();
        // Rilanciarla non deve cambiare niente: le migrazioni dei contenuti
        // girano a ogni rilascio.
        $migrazione->up();

        $pagina->refresh();

        $this->assertSame('Public/Affiliazioni', $pagina->template);
        $this->assertSame('<p>Un progetto per crescere insieme</p>', $pagina->getTranslation('content', 'it'));
        $this->assertSame([], $pagina->getTranslation('content_data', 'it')['affiliates']);
        $this->assertSame('Le società del progetto', $pagina->getTranslation('content_data', 'it')['clubs_heading']);
        $this->assertSame('The clubs in the project', $pagina->getTranslation('content_data', 'en')['clubs_heading']);
    }

    #[Test]
    public function il_racconto_perde_solo_le_sezioni_che_elencavano_i_partner_a_testo(): void
    {
        $pagina = Page::create([
            'title' => ['it' => 'Progetto Affiliazioni', 'en' => 'Affiliation Project'],
            'slug' => 'affiliazioni',
            'template' => 'Public/ContentPage',
            'status' => PostStatus::Published,
            'content' => [
                'it' => '<h2>Un progetto per crescere insieme</h2><p>Il racconto.</p>'
                    .'<h2>Main Partner</h2><p>Fusion Team Volley, Vola Valley.</p>'
                    .'<h2>Partner Ufficiali</h2><p>Nottolini Volley, La Spezia.</p>'
                    .'<h2>Come aderire</h2><p>Per informazioni: 334 6085983</p>',
                'en' => '<h2>Why join</h2><ul><li>Ticketing</li></ul>'
                    .'<h2>Official Partners</h2><p>Nottolini Volley, La Spezia.</p>',
            ],
            'content_data' => ['it' => [], 'en' => []],
        ]);

        $migrazione = require database_path('migrations/2026_09_18_090000_pagina_affiliazioni_con_i_loghi_delle_societa.php');
        $migrazione->up();

        $pagina->refresh();

        $this->assertSame(
            '<h2>Un progetto per crescere insieme</h2><p>Il racconto.</p><h2>Come aderire</h2><p>Per informazioni: 334 6085983</p>',
            $pagina->getTranslation('content', 'it'),
        );
        // L'elenco "Perché affiliarsi" non e' un elenco di societa': resta.
        $this->assertSame(
            '<h2>Why join</h2><ul><li>Ticketing</li></ul>',
            $pagina->getTranslation('content', 'en'),
        );
    }

    #[Test]
    public function le_societa_arrivano_al_frontend_con_il_logo_come_indirizzo_pubblico(): void
    {
        $this->paginaConSocieta([
            ['name' => 'Volley Appennino', 'tier' => 'affiliated', 'url' => 'https://volleyappennino.example', 'logo' => 'affiliazioni/appennino.png'],
            ['name' => 'Fusion Team Volley', 'tier' => 'main', 'url' => 'https://fusion.example', 'logo' => null],
        ]);

        $this->get('/youth/affiliazioni')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Public/Affiliazioni')
                ->where('page.content_data.affiliates.0.name', 'Volley Appennino')
                ->where('page.content_data.affiliates.0.logo', Storage::url('affiliazioni/appennino.png')));
    }

    #[Test]
    public function dal_pannello_le_societa_si_salvano_come_elenco(): void
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        $pagina = $this->paginaConSocieta([]);

        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('data.content_data.affiliates', [
                ['name' => 'Nottolini Volley', 'tier' => 'official', 'url' => 'https://nottolini.example', 'logo' => null],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $societa = $pagina->refresh()->getTranslation('content_data', 'it')['affiliates'];

        $this->assertTrue(array_is_list($societa));
        $this->assertSame('Nottolini Volley', $societa[0]['name']);
        $this->assertSame('official', $societa[0]['tier']);
    }

    /**
     * @param  list<array<string, mixed>>  $societa
     */
    private function paginaConSocieta(array $societa): Page
    {
        return Page::create([
            'title' => ['it' => 'Progetto Affiliazioni'],
            'slug' => 'affiliazioni',
            'template' => 'Public/Affiliazioni',
            'status' => PostStatus::Published,
            'content_data' => ['it' => [
                'clubs_heading' => 'Le società del progetto',
                'affiliates' => $societa,
            ]],
        ]);
    }
}
