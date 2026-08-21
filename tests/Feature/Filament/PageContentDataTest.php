<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertCount(1, $contenuti['projects'] ?? []);
        $this->assertCount(1, $contenuti['impact_stats'] ?? []);
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
        $this->assertCount(1, $contenuti['projects'] ?? []);
        $this->assertCount(1, $contenuti['impact_stats'] ?? []);
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
        $this->assertCount(1, $contenuti['impact_stats'] ?? []);
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
}
