<?php

namespace Tests\Feature\Filament;

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
 * Salvare una pagina non deve cancellare quello che non si è toccato.
 *
 * I campi si chiamano `content_data.hero_badge`, `content_data.projects`…:
 * Filament li ricompone in un array unico, ma un Repeater con quello schema di
 * nome riscriveva `content_data` per intero portandosi via i fratelli. Bastava
 * aprire una pagina e premere Salva per svuotarla, e in redazione si vedeva
 * come "modifico una cosa e sparisce tutto".
 */
class PageContentDataTest extends TestCase
{
    use RefreshDatabase;

    private function redattore(): User
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        return $utente->refresh();
    }

    private function paginaSociale(array $contenuti = []): Page
    {
        return Page::create([
            'title' => ['it' => 'Progetti Sociali'],
            'slug' => 'prova-'.uniqid(),
            'template' => 'Public/Sociale',
            'status' => 'publish',
            'content_data' => ['it' => array_merge([
                'hero_badge' => 'PROGETTI SOCIALI',
                'mission_title' => 'La Nostra Missione',
                'projects' => [
                    ['title' => 'Volley 4 All', 'tag' => 'INCLUSIONE', 'icon' => '🏐', 'color' => 'savino-blue', 'description' => 'Pallavolo per tutti'],
                ],
                'impact_stats' => [
                    ['value' => '500+', 'label' => 'Ragazzi Coinvolti'],
                ],
            ], $contenuti)],
        ]);
    }

    #[Test]
    public function un_salvataggio_senza_modifiche_non_cancella_niente(): void
    {
        $this->actingAs($this->redattore());
        $pagina = $this->paginaSociale();

        Livewire::test(EditPage::class, ['record' => $pagina->id])->call('save');

        $contenuti = $pagina->refresh()->getTranslation('content_data', 'it');

        $this->assertSame('PROGETTI SOCIALI', $contenuti['hero_badge'] ?? null);
        $this->assertSame('La Nostra Missione', $contenuti['mission_title'] ?? null);
        $this->assertElenco($contenuti['projects'] ?? null, 1);
        $this->assertElenco($contenuti['impact_stats'] ?? null, 1);
    }

    /**
     * Un Repeater deve tornare in archivio come elenco: lo stato grezzo di
     * Livewire e' una mappa `{uuid: voce}` e, salvata cosi', i template la
     * scartavano e la sezione spariva dal sito.
     */
    private function assertElenco(mixed $valore, int $voci): void
    {
        $this->assertIsArray($valore);
        $this->assertTrue(array_is_list($valore), 'l\'elenco e\' stato salvato come mappa con chiavi UUID');
        $this->assertCount($voci, $valore);
    }

    #[Test]
    public function modificare_un_campo_lascia_stare_gli_altri(): void
    {
        $this->actingAs($this->redattore());
        $pagina = $this->paginaSociale();

        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('data.content_data.hero_badge', 'NUOVA ETICHETTA')
            ->call('save');

        $contenuti = $pagina->refresh()->getTranslation('content_data', 'it');

        $this->assertSame('NUOVA ETICHETTA', $contenuti['hero_badge'] ?? null);
        $this->assertSame('La Nostra Missione', $contenuti['mission_title'] ?? null);
        $this->assertElenco($contenuti['projects'] ?? null, 1);
        $this->assertElenco($contenuti['impact_stats'] ?? null, 1);
    }

    /**
     * Le chiavi degli altri modelli di pagina non compaiono nemmeno nel modulo:
     * un salvataggio non le deve toccare.
     */
    #[Test]
    public function le_chiavi_di_altri_modelli_restano_dove_sono(): void
    {
        $this->actingAs($this->redattore());
        $pagina = $this->paginaSociale(['press_kits' => [['title' => 'Brand Book', 'file' => 'press-kit/brand.pdf']]]);

        Livewire::test(EditPage::class, ['record' => $pagina->id])->call('save');

        $contenuti = $pagina->refresh()->getTranslation('content_data', 'it');

        $this->assertCount(1, $contenuti['press_kits'] ?? []);
    }

    /**
     * Il contrario deve restare possibile: se la redazione svuota un elenco,
     * quello deve restare vuoto e non ricomparire al salvataggio dopo.
     */
    #[Test]
    public function svuotare_un_elenco_resta_svuotato(): void
    {
        $this->actingAs($this->redattore());
        $pagina = $this->paginaSociale();

        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('data.content_data.projects', [])
            ->call('save');

        $contenuti = $pagina->refresh()->getTranslation('content_data', 'it');

        $this->assertSame([], $contenuti['projects'] ?? null);
        // ma il resto della pagina è ancora al suo posto
        $this->assertSame('PROGETTI SOCIALI', $contenuti['hero_badge'] ?? null);
        $this->assertElenco($contenuti['impact_stats'] ?? null, 1);
    }

    /**
     * L'altra lingua non si tocca: si modifica quella scelta nel pannello.
     */
    #[Test]
    public function l_altra_lingua_non_viene_toccata(): void
    {
        $this->actingAs($this->redattore());

        $pagina = Page::create([
            'title' => ['it' => 'Progetti', 'en' => 'Projects'],
            'slug' => 'prova-lingue',
            'template' => 'Public/Sociale',
            'status' => 'publish',
            'content_data' => [
                'it' => ['hero_badge' => 'PROGETTI', 'mission_title' => 'Missione'],
                'en' => ['hero_badge' => 'PROJECTS', 'mission_title' => 'Mission'],
            ],
        ]);

        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('data.content_data.hero_badge', 'CAMBIATO')
            ->call('save');

        $pagina->refresh();

        $this->assertSame('CAMBIATO', $pagina->getTranslation('content_data', 'it')['hero_badge'] ?? null);
        $this->assertSame('PROJECTS', $pagina->getTranslation('content_data', 'en')['hero_badge'] ?? null);
    }

    /**
     * Rinominare una voce di un elenco dal pannello: e' il caso segnalato
     * dalla redazione (il piano abbonamento rinominato e la campagna che
     * online risultava "non ancora aperta").
     */
    #[Test]
    public function modificare_una_voce_di_un_elenco_lo_lascia_elenco(): void
    {
        $this->actingAs($this->redattore());
        $pagina = $this->paginaSociale();

        $componente = Livewire::test(EditPage::class, ['record' => $pagina->id]);
        $chiave = array_key_first($componente->get('data.content_data.projects'));

        $componente
            ->set("data.content_data.projects.{$chiave}.title", 'Volley 4 All — nuovo nome')
            ->call('save')
            ->assertHasNoErrors();

        $contenuti = $pagina->refresh()->getTranslation('content_data', 'it');

        $this->assertElenco($contenuti['projects'] ?? null, 1);
        $this->assertSame('Volley 4 All — nuovo nome', $contenuti['projects'][0]['title']);
    }

    /**
     * Il file di un elenco (cartella stampa, documento) si salva come
     * percorso, non come mappa `{uuid: percorso}`: altrimenti il sito non
     * trova il file da scaricare.
     */
    #[Test]
    public function il_file_di_un_elenco_si_salva_come_percorso(): void
    {
        $this->actingAs($this->redattore());
        // Il form scarta i percorsi che sul disco non esistono.
        Storage::fake(config('filament.default_filesystem_disk'))->put('documenti/bilancio.pdf', '%PDF-1.4');

        $pagina = Page::create([
            'title' => ['it' => 'Bilancio'],
            'slug' => 'prova-documenti-'.uniqid(),
            'template' => 'Public/ContentPage',
            'status' => 'publish',
            'content_data' => ['it' => [
                'documents' => [['title' => 'Bilancio 2024/2025', 'file' => 'documenti/bilancio.pdf']],
            ]],
        ]);

        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->call('save')
            ->assertHasNoErrors();

        $contenuti = $pagina->refresh()->getTranslation('content_data', 'it');

        $this->assertElenco($contenuti['documents'] ?? null, 1);
        $this->assertSame('documenti/bilancio.pdf', $contenuti['documents'][0]['file']);
    }

    /**
     * Per la lingua non attiva il plugin translatable passa lo stato grezzo
     * di Livewire: anche quella deve finire in archivio come elenco.
     */
    #[Test]
    public function anche_l_altra_lingua_salva_gli_elenchi_come_elenchi(): void
    {
        $this->actingAs($this->redattore());

        $pagina = Page::create([
            'title' => ['it' => 'Progetti', 'en' => 'Projects'],
            'slug' => 'prova-lingue-'.uniqid(),
            'template' => 'Public/Sociale',
            'status' => 'publish',
            'content_data' => [
                'it' => ['hero_badge' => 'IT', 'projects' => [['title' => 'Volley 4 All', 'tag' => 'INCLUSIONE', 'icon' => '🏐', 'color' => 'savino-blue', 'description' => 'Pallavolo per tutti']]],
                'en' => ['hero_badge' => 'EN', 'projects' => [['title' => 'Volley 4 All', 'tag' => 'INCLUSION', 'icon' => '🏐', 'color' => 'savino-blue', 'description' => 'Volleyball for all']]],
            ],
        ]);

        // Si passa dall'inglese e si torna all'italiano: lo stato inglese
        // resta nel componente come "altra lingua" e viene salvato con l'italiano.
        Livewire::test(EditPage::class, ['record' => $pagina->id])
            ->set('activeLocale', 'en')
            ->set('data.content_data.hero_badge', 'EN MODIFICATO')
            ->set('activeLocale', 'it')
            ->call('save')
            ->assertHasNoErrors();

        $pagina->refresh();
        $inglese = $pagina->getTranslation('content_data', 'en');
        $italiano = $pagina->getTranslation('content_data', 'it');

        $this->assertSame('EN MODIFICATO', $inglese['hero_badge'] ?? null);
        $this->assertElenco($inglese['projects'] ?? null, 1);
        $this->assertElenco($italiano['projects'] ?? null, 1);
    }
}
