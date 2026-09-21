<?php

namespace Tests\Feature\Filament;

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
 * Gli elenchi che non hanno traduzione si salvano in tutte le lingue.
 *
 * Una societa' affiliata e' un nome, un livello, un sito e un logo: le stesse
 * cose in italiano e in inglese. Finche' ogni lingua ne teneva una copia, chi
 * modificava l'elenco con il pannello in inglese non vedeva cambiare niente
 * sul sito italiano — e' successo il 21/09/2026 con una societa' spostata da
 * Partner Ufficiale a Societa' Affiliata, salvata tre volte senza effetto.
 */
class ChiaviComuniAlleLingueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        Storage::fake('local');
        Storage::fake(config('filament.default_filesystem_disk'));
    }

    /**
     * @param  list<array<string, mixed>>  $societa
     */
    private function paginaAffiliazioni(array $societa): Page
    {
        return Page::create([
            'title' => ['it' => 'Progetto Affiliazioni', 'en' => 'Affiliation Project'],
            'slug' => 'prova-affiliazioni-'.uniqid(),
            'template' => 'Public/Affiliazioni',
            'status' => PostStatus::Published,
            'content_data' => [
                'it' => ['hero_label' => 'AFFILIAZIONI', 'affiliates' => $societa],
                'en' => ['hero_label' => 'AFFILIATIONS', 'affiliates' => $societa],
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dueSocieta(): array
    {
        return [
            ['name' => 'Nottolini Volley', 'tier' => 'official', 'url' => 'https://www.nottolini.it', 'logo' => 'affiliazioni/nottolini.png'],
            ['name' => 'Volley Appennino', 'tier' => 'affiliated', 'url' => null, 'logo' => null],
        ];
    }

    #[Test]
    public function il_livello_cambiato_in_inglese_arriva_anche_all_italiano(): void
    {
        $pagina = $this->paginaAffiliazioni($this->dueSocieta());

        $componente = Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('activeLocale', 'en');

        $chiave = array_key_first($componente->get('data.content_data.affiliates'));

        $componente
            ->set("data.content_data.affiliates.{$chiave}.tier", 'affiliated')
            ->call('save')
            ->assertHasNoErrors();

        $pagina->refresh();

        foreach (['it', 'en'] as $lingua) {
            $societa = $pagina->getTranslation('content_data', $lingua)['affiliates'] ?? null;

            $this->assertIsArray($societa, "l'elenco in $lingua non e' un elenco");
            $this->assertTrue(array_is_list($societa), "l'elenco in $lingua e' diventato una mappa");
            $this->assertSame('Nottolini Volley', $societa[0]['name'] ?? null);
            $this->assertSame('affiliated', $societa[0]['tier'] ?? null, "il livello non e' arrivato in $lingua");
        }
    }

    #[Test]
    public function una_societa_tolta_in_italiano_sparisce_anche_dall_inglese(): void
    {
        $pagina = $this->paginaAffiliazioni($this->dueSocieta());

        $componente = Livewire::test(EditPage::class, ['record' => $pagina->id]);
        $chiavi = array_keys($componente->get('data.content_data.affiliates'));

        $componente
            ->set('data.content_data.affiliates', [$chiavi[0] => $componente->get("data.content_data.affiliates.{$chiavi[0]}")])
            ->call('save')
            ->assertHasNoErrors();

        $pagina->refresh();

        foreach (['it', 'en'] as $lingua) {
            $societa = $pagina->getTranslation('content_data', $lingua)['affiliates'] ?? null;

            $this->assertCount(1, $societa, "in $lingua la societa' tolta e' ancora in elenco");
            $this->assertSame('Nottolini Volley', $societa[0]['name'] ?? null);
        }
    }

    /**
     * L'allineamento riguarda solo le chiavi senza traduzione: un testo
     * scritto in inglese non deve finire nella pagina italiana.
     */
    #[Test]
    public function i_testi_restano_quelli_della_loro_lingua(): void
    {
        $pagina = $this->paginaAffiliazioni($this->dueSocieta());

        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('activeLocale', 'en')
            ->set('data.content_data.hero_label', 'AFFILIATIONS 2027')
            ->call('save')
            ->assertHasNoErrors();

        $pagina->refresh();

        $this->assertSame('AFFILIATIONS 2027', $pagina->getTranslation('content_data', 'en')['hero_label'] ?? null);
        $this->assertSame('AFFILIAZIONI', $pagina->getTranslation('content_data', 'it')['hero_label'] ?? null);
    }

    /**
     * Le chiavi comuni che il modulo non mostra — un'altra pagina, un altro
     * modello — non si toccano: il salvataggio riscrive solo quello che ha in
     * mano.
     */
    #[Test]
    public function le_chiavi_di_un_altro_modello_di_pagina_restano_dove_sono(): void
    {
        $pagina = Page::create([
            'title' => ['it' => 'Club Race', 'en' => 'Club Race'],
            'slug' => 'prova-club-race-'.uniqid(),
            'template' => 'Public/ClubRace',
            'status' => PostStatus::Published,
            'content_data' => [
                'it' => ['standings' => [['club' => 'Volley Appennino', 'points' => '120']], 'affiliates' => [['name' => 'Nottolini Volley', 'tier' => 'official']]],
                'en' => ['standings' => [['club' => 'Volley Appennino', 'points' => '120']], 'affiliates' => [['name' => 'Nottolini Volley', 'tier' => 'official']]],
            ],
        ]);

        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('data.content_data.standings_title', 'Classifica 2027')
            ->call('save')
            ->assertHasNoErrors();

        $inglese = $pagina->refresh()->getTranslation('content_data', 'en');

        $this->assertSame('official', $inglese['affiliates'][0]['tier'] ?? null);
    }
}
