<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Copre i due difetti riscontrati in produzione sulle Pagine CMS:
 * il contenuto che non si ripopola nell'editor (rischio di cancellare
 * il testo pubblicato salvando) e il salvataggio in creazione che
 * lamenta il titolo mancante.
 */
class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user;
    }

    public function test_edit_form_is_filled_with_the_stored_content(): void
    {
        $page = Page::factory()->create([
            'title' => ['it' => 'Storia', 'en' => 'History'],
            'content' => ['it' => '<h2>Le Origini</h2><p>Fondata nel 1982.</p>', 'en' => '<h2>Origins</h2>'],
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->getKey()])
            ->assertFormSet([
                'title' => 'Storia',
                'content' => '<h2>Le Origini</h2><p>Fondata nel 1982.</p>',
            ]);
    }

    /**
     * Le pagine importate da WordPress possono avere le colonne translatable
     * in testo semplice invece che in JSON: il form deve mostrarle comunque,
     * altrimenti un salvataggio cancella il contenuto pubblicato.
     */
    public function test_edit_form_is_filled_when_the_stored_content_is_legacy_plain_text(): void
    {
        $page = Page::factory()->create();

        \DB::table('pages')->where('id', $page->getKey())->update([
            'title' => 'Storia',
            'content' => '<h2>Le Origini</h2><p>Testo legacy non JSON.</p>',
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->getKey()])
            ->assertFormSet([
                'content' => '<h2>Le Origini</h2><p>Testo legacy non JSON.</p>',
            ]);
    }

    public function test_saving_an_untouched_edit_form_preserves_the_published_content(): void
    {
        $page = Page::factory()->create([
            'title' => ['it' => 'Storia'],
            'content' => ['it' => '<p>Contenuto pubblicato.</p>'],
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('<p>Contenuto pubblicato.</p>', $page->refresh()->getTranslation('content', 'it'));
    }

    public function test_a_page_can_be_created_with_title_and_slug(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'QA Pagina di prova',
                'slug' => 'qa-pagina-di-prova',
                'status' => 'draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pages', ['slug' => 'qa-pagina-di-prova']);
    }
}
