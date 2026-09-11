<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
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
 * I documenti della pagina Safeguarding arrivavano dal seeder con il file
 * "#": un segnaposto che il form scarta, lasciando il campo vuoto. Con il
 * file obbligatorio, caricare il PDF di un documento solo non bastava a
 * salvare: l'altro restava vuoto e bloccava tutto.
 */
class SafeguardingDocumentiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function si_carica_il_pdf_di_un_documento_anche_se_l_altro_non_ce_l_ha(): void
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        Storage::fake('local');
        Storage::fake(config('filament.default_filesystem_disk'));

        $pagina = Page::create([
            'title' => ['it' => 'Safeguarding'],
            'slug' => 'prova-safeguarding-'.uniqid(),
            'template' => 'Public/Societa/Safeguarding',
            'status' => 'publish',
            'content_data' => ['it' => [
                'documents' => [
                    ['title' => 'Modello Organizzativo', 'file' => '#', 'icon' => '', 'description' => ''],
                    ['title' => 'Codice di Condotta', 'file' => '#', 'icon' => '', 'description' => ''],
                ],
            ]],
        ]);

        $componente = Livewire::test(EditPage::class, ['record' => $pagina->id]);
        $chiavi = array_keys($componente->get('data.content_data.documents'));

        $componente
            ->set("data.content_data.documents.{$chiavi[0]}.file", [UploadedFile::fake()->createWithContent('modello.pdf', "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")])
            ->call('save');

        $this->assertSame([], $componente->errors()->toArray(), 'chiavi: '.implode(',', $chiavi));

        $documenti = $pagina->refresh()->getTranslation('content_data', 'it')['documents'];

        $this->assertCount(2, $documenti);
        $this->assertSame('safeguarding/modello.pdf', $documenti[0]['file']);
        Storage::disk(config('filament.default_filesystem_disk'))->assertExists('safeguarding/modello.pdf');
    }
}
