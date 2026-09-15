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
 * La pagina Convenzioni elenca i partner che offrono agevolazioni agli
 * abbonati, inseriti dal pannello.
 */
class ConvenzioniTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_pagina_di_contenuto_diventa_convenzioni_senza_perdere_il_testo(): void
    {
        $pagina = Page::create([
            'title' => ['it' => 'Convenzioni', 'en' => 'Partner offers'],
            'slug' => 'convenzioni',
            'template' => 'Public/ContentPage',
            'status' => PostStatus::Published,
            'content' => ['it' => '<p>Sconti per gli abbonati</p>'],
            'content_data' => ['it' => ['button_url' => null], 'en' => ['button_url' => null]],
        ]);

        $migrazione = require database_path('migrations/2026_09_15_101000_pagina_convenzioni_con_i_partner.php');
        $migrazione->up();
        $migrazione->up();

        $pagina->refresh();

        $this->assertSame('Public/Convenzioni', $pagina->template);
        $this->assertSame('<p>Sconti per gli abbonati</p>', $pagina->getTranslation('content', 'it'));
        $this->assertSame([], $pagina->getTranslation('content_data', 'it')['partners']);
        $this->assertSame('I nostri partner', $pagina->getTranslation('content_data', 'it')['partners_heading']);
        $this->assertSame('Our partners', $pagina->getTranslation('content_data', 'en')['partners_heading']);
    }

    #[Test]
    public function i_partner_arrivano_al_frontend_con_il_logo_come_indirizzo_pubblico(): void
    {
        Page::create([
            'title' => ['it' => 'Convenzioni'],
            'slug' => 'convenzioni',
            'template' => 'Public/Convenzioni',
            'status' => PostStatus::Published,
            'content_data' => ['it' => [
                'partners_heading' => 'I nostri partner',
                'partners' => [
                    ['name' => 'Trattoria', 'url' => 'https://trattoria.example', 'discount' => '10%', 'description' => 'Sul conto', 'how_to_use' => 'Mostra la tessera', 'logo' => 'convenzioni/trattoria.png'],
                ],
            ]],
        ]);

        $this->get('/ticketing/convenzioni')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Public/Convenzioni')
                ->where('page.content_data.partners.0.name', 'Trattoria')
                ->where('page.content_data.partners.0.logo', Storage::url('convenzioni/trattoria.png')));
    }

    #[Test]
    public function dal_pannello_i_partner_si_salvano_come_elenco(): void
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        $pagina = Page::create([
            'title' => ['it' => 'Convenzioni'],
            'slug' => 'convenzioni',
            'template' => 'Public/Convenzioni',
            'status' => PostStatus::Published,
            'content_data' => ['it' => ['partners' => []]],
        ]);

        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('data.content_data.partners', [
                ['name' => 'Trattoria', 'url' => 'https://trattoria.example', 'discount' => '10%', 'description' => 'Sul conto', 'how_to_use' => 'Mostra la tessera', 'logo' => null],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $partner = $pagina->refresh()->getTranslation('content_data', 'it')['partners'];

        $this->assertTrue(array_is_list($partner));
        $this->assertSame('Trattoria', $partner[0]['name']);
        $this->assertSame('10%', $partner[0]['discount']);
    }
}
