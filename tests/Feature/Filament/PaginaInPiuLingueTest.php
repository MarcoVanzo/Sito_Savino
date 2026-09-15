<?php

namespace Tests\Feature\Filament;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il plugin delle traduzioni tiene le lingue non attive "da parte", grezze:
 * cambiando lingua o salvando devono passare dal modulo senza effetti
 * collaterali sui campi comuni e senza confondere una lingua con l'altra.
 */
class PaginaInPiuLingueTest extends TestCase
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

    private function paginaTicketing(array $it = [], array $en = []): Page
    {
        return Page::create([
            'title' => ['it' => 'Biglietteria', 'en' => 'Ticketing'],
            'slug' => 'prova-'.uniqid(),
            'template' => 'Public/Ticketing',
            'status' => PostStatus::Published,
            'content_data' => ['it' => $it, 'en' => $en],
        ]);
    }

    private function pdf(string $nome): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    #[Test]
    public function cambiare_lingua_non_ripristina_i_campi_comuni_a_tutte_le_lingue(): void
    {
        $genitore = $this->paginaTicketing();
        $pagina = $this->paginaTicketing();
        $pagina->update(['parent_id' => $genitore->id]);

        $componente = Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('data.parent_id', null)
            ->set('activeLocale', 'en');

        $this->assertNull($componente->get('data.parent_id'), 'la pagina genitore svuotata è tornata quella in archivio');
    }

    #[Test]
    public function il_file_caricato_nell_altra_lingua_si_salva_una_volta_sola(): void
    {
        $pagina = $this->paginaTicketing(['feature_title' => 'Biglietti'], ['feature_title' => 'Tickets']);
        $disco = Storage::disk(config('filament.default_filesystem_disk'));

        $componente = Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('activeLocale', 'en')
            ->set('data.content_data.gift_card_image', [UploadedFile::fake()->image('gift.png', 390, 390)])
            ->set('activeLocale', 'it')
            ->call('save')
            ->assertHasNoErrors();

        $primo = $pagina->refresh()->getTranslation('content_data', 'en')['gift_card_image'];
        $this->assertIsString($primo);
        $this->assertStringStartsWith('ticketing/', $primo);
        $disco->assertExists($primo);

        $componente->call('save')->assertHasNoErrors();

        $this->assertSame($primo, $pagina->refresh()->getTranslation('content_data', 'en')['gift_card_image']);
        $this->assertCount(1, $disco->files('ticketing'), 'il file è stato ricopiato al secondo salvataggio');
    }

    #[Test]
    public function le_chiavi_degli_altri_modelli_non_passano_da_una_lingua_all_altra(): void
    {
        $pagina = $this->paginaTicketing(
            ['feature_title' => 'Biglietti', 'chiave_di_altro_template' => 'solo-it'],
            ['feature_title' => 'Tickets'],
        );

        Livewire::test(EditPage::class, ['record' => $pagina->id])->call('save')->assertHasNoErrors();

        $pagina->refresh();
        $this->assertSame('solo-it', $pagina->getTranslation('content_data', 'it')['chiave_di_altro_template']);
        $this->assertArrayNotHasKey('chiave_di_altro_template', $pagina->getTranslation('content_data', 'en'));
        $this->assertSame('Tickets', $pagina->getTranslation('content_data', 'en')['feature_title']);
    }

    #[Test]
    public function una_pagina_nuova_salva_elenchi_e_file_anche_della_lingua_non_attiva(): void
    {
        $slug = 'nuova-'.uniqid();

        Livewire::test(CreatePage::class)
            ->set('data.template', 'Public/Ticketing')
            ->set('activeLocale', 'en')
            ->set('data.title', 'Ticketing')
            ->set('data.slug', $slug)
            ->set('data.content_data.benefits', [['text' => 'Discounts']])
            ->set('data.content_data.gift_card_image', [UploadedFile::fake()->image('gift.png', 390, 390)])
            ->set('activeLocale', 'it')
            ->set('data.title', 'Biglietteria')
            ->set('data.slug', $slug)
            ->call('create')
            ->assertHasNoErrors();

        $pagina = Page::where('slug', $slug)->firstOrFail();
        $en = $pagina->getTranslation('content_data', 'en');

        $this->assertTrue(array_is_list($en['benefits']));
        $this->assertSame('Discounts', $en['benefits'][0]['text']);
        $this->assertIsString($en['gift_card_image']);
        Storage::disk(config('filament.default_filesystem_disk'))->assertExists($en['gift_card_image']);
    }
}
