<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La pagina Safeguarding pubblica gli stessi PDF di Documenti Legali. Aveva
 * una copia propria dei file: sostituito un PDF in Documenti Legali, il footer
 * mostrava quello nuovo e Safeguarding quello vecchio.
 */
class SafeguardingDocumentiLegaliTest extends TestCase
{
    use RefreshDatabase;

    private function pagina(array $documenti): Page
    {
        return Page::create([
            'title' => ['it' => 'Safeguarding'],
            'slug' => 'prova-safeguarding-'.uniqid(),
            'template' => 'Public/Societa/Safeguarding',
            'status' => 'publish',
            'content_data' => ['it' => ['documents' => $documenti]],
        ]);
    }

    #[Test]
    public function il_documento_che_rimanda_a_documenti_legali_segue_il_file_caricato_li(): void
    {
        SiteSetting::set('legal.modello_organizzativo', 'legal/modello-2026.pdf', 'general');

        $pagina = $this->pagina([
            ['title' => 'Modello Organizzativo', 'documento_legale' => 'modello_organizzativo'],
            ['title' => 'Altro', 'file' => 'safeguarding/altro.pdf'],
        ]);

        $documenti = $pagina->datiPerIlFrontend()['content_data']['documents'];
        $this->assertSame(Storage::url('legal/modello-2026.pdf'), $documenti[0]['file']);
        $this->assertSame(Storage::url('safeguarding/altro.pdf'), $documenti[1]['file']);

        SiteSetting::set('legal.modello_organizzativo', 'legal/modello-2027.pdf', 'general');

        $documenti = $pagina->refresh()->datiPerIlFrontend()['content_data']['documents'];
        $this->assertSame(Storage::url('legal/modello-2027.pdf'), $documenti[0]['file']);
    }

    #[Test]
    public function senza_pdf_in_documenti_legali_il_documento_resta_senza_download(): void
    {
        $pagina = $this->pagina([
            ['title' => 'Protocollo Bullismo', 'documento_legale' => 'protocollo_bullismo', 'file' => 'safeguarding/vecchio.pdf'],
        ]);

        $this->assertNull($pagina->datiPerIlFrontend()['content_data']['documents'][0]['file']);
    }

    #[Test]
    public function dal_pannello_si_sceglie_il_documento_legale(): void
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        $pagina = $this->pagina([['title' => 'Protocollo Razzismo', 'file' => null, 'icon' => '', 'description' => '']]);

        $componente = Livewire::test(EditPage::class, ['record' => $pagina->id]);
        $chiavi = array_keys($componente->get('data.content_data.documents'));

        $componente
            ->set("data.content_data.documents.{$chiavi[0]}.documento_legale", 'protocollo_razzismo')
            ->call('save');

        $this->assertSame([], $componente->errors()->toArray());

        $documento = $pagina->refresh()->getTranslation('content_data', 'it')['documents'][0];
        $this->assertSame('protocollo_razzismo', $documento['documento_legale']);
        $this->assertArrayNotHasKey('file', $documento);
    }

    #[Test]
    public function la_migrazione_collega_i_quattro_pdf_e_lascia_stare_il_resto(): void
    {
        $voce = fn (string $titolo, ?string $file) => ['title' => $titolo, 'file' => $file, 'icon' => '', 'description' => ''];

        DB::table('pages')->where('slug', 'safeguarding')->delete();
        Page::create([
            'title' => ['it' => 'Safeguarding', 'en' => 'Safeguarding'],
            'slug' => 'safeguarding',
            'template' => 'Public/Societa/Safeguarding',
            'status' => 'publish',
            'content_data' => [
                'it' => ['documents' => [
                    $voce('Modello', 'safeguarding/Modello-Organizzativo_compressed.pdf'),
                    $voce('Codice', 'safeguarding/Protocollo-1-Codice-di-condotta.pdf'),
                    $voce('Bullismo', 'legal/Protocollo-2-Bullismo-e-cyberbullismo.pdf'),
                    $voce('Razzismo', 'legal/Protocollo-3-Razzismo-e-xenofobia.pdf'),
                    $voce('Altro', 'safeguarding/altro.pdf'),
                ]],
                'en' => ['documents' => [
                    $voce('Model', 'safeguarding/Modello-Organizzativo_compressed.pdf'),
                ]],
            ],
        ]);

        $migrazione = require database_path('migrations/2026_09_26_180000_safeguarding_prende_i_pdf_dai_documenti_legali.php');
        $migrazione->up();
        $migrazione->up();

        $pagina = Page::where('slug', 'safeguarding')->first();
        $it = $pagina->getTranslation('content_data', 'it')['documents'];

        $this->assertSame(
            ['modello_organizzativo', 'codice_tutela_minori', 'protocollo_bullismo', 'protocollo_razzismo', null],
            array_map(fn ($d) => $d['documento_legale'] ?? null, $it)
        );
        $this->assertArrayNotHasKey('file', $it[0]);
        $this->assertSame('safeguarding/altro.pdf', $it[4]['file']);
        $this->assertSame('Modello', $it[0]['title']);
        $this->assertSame('modello_organizzativo', $pagina->getTranslation('content_data', 'en')['documents'][0]['documento_legale']);
    }
}
