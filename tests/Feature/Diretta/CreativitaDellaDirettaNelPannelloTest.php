<?php

namespace Tests\Feature\Diretta;

use App\Enums\TipoCreativita;
use App\Enums\UserRole;
use App\Enums\UsoCreativita;
use App\Filament\Pages\Settings\DirettaSettingsPage;
use App\Filament\Resources\LiveCreativeResource\Pages\CreateLiveCreative;
use App\Models\LiveCreative;
use App\Models\SiteSetting;
use App\Models\Sponsor;
use App\Models\User;
use App\Support\Diretta\ImpostazioniDellaDiretta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreativitaDellaDirettaNelPannelloTest extends TestCase
{
    use RefreshDatabase;

    private function utente(UserRole $ruolo): User
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => $ruolo, 'is_active' => true])->save();

        return $utente->refresh();
    }

    private function png(int $larghezza, int $altezza): UploadedFile
    {
        ob_start();
        imagepng(imagecreatetruecolor($larghezza, $altezza), null, 9);

        return UploadedFile::fake()->createWithContent('cornice.png', (string) ob_get_clean());
    }

    #[Test]
    public function l_interruttore_generale_nasce_spento(): void
    {
        $this->assertSame('diretta', SiteSetting::query()->where('key', 'diretta_sponsor_abilitata')->value('group'));
        $this->assertFalse(ImpostazioniDellaDiretta::abilitata());
    }

    #[Test]
    public function la_comunicazione_carica_una_cornice_in_formato(): void
    {
        Storage::fake('public');
        $this->actingAs($this->utente(UserRole::CommunicationManager));
        $sponsor = Sponsor::factory()->create();

        Livewire::test(CreateLiveCreative::class)
            ->fillForm([
                'sponsor_id' => $sponsor->id,
                'nome' => 'Cornice Estra',
                'uso' => UsoCreativita::Cornice->value,
                'tipo' => TipoCreativita::Immagine->value,
                'durata_secondi' => 10,
                'testo_alternativo' => 'Estra, energia per lo sport',
                'link_url' => 'https://www.estra.it/',
            ])
            ->set('data.contenuto.nuovo', $this->png(1920, 1080))
            ->call('create')
            ->assertHasNoFormErrors();

        $creativita = LiveCreative::sole();
        $this->assertSame(UsoCreativita::Cornice, $creativita->uso);
        $this->assertNotNull($creativita->getFirstMedia(LiveCreative::COLLEZIONE_CONTENUTO));
        $this->assertTrue($creativita->valida());
    }

    #[Test]
    public function una_cornice_fuori_misura_si_rifiuta_con_le_misure_giuste(): void
    {
        Storage::fake('public');
        $this->actingAs($this->utente(UserRole::CommunicationManager));

        Livewire::test(CreateLiveCreative::class)
            ->fillForm([
                'sponsor_id' => Sponsor::factory()->create()->id,
                'nome' => 'Cornice sbagliata',
                'uso' => UsoCreativita::Cornice->value,
                'tipo' => TipoCreativita::Immagine->value,
                'durata_secondi' => 10,
                'testo_alternativo' => 'Sponsor',
            ])
            ->set('data.contenuto.nuovo', $this->png(1280, 720))
            ->call('create')
            ->assertHasFormErrors(['contenuto']);

        $this->assertSame(0, LiveCreative::count());
    }

    #[Test]
    public function la_durata_di_uno_spot_video_si_legge_dal_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->utente(UserRole::CommunicationManager));
        $video = UploadedFile::fake()->createWithContent(
            'spot.mp4',
            (string) file_get_contents(base_path('tests/Fixtures/Diretta/spot-1920x1080-2s.mp4')),
        );

        Livewire::test(CreateLiveCreative::class)
            ->fillForm([
                'sponsor_id' => Sponsor::factory()->create()->id,
                'nome' => 'Spot Estra',
                'uso' => UsoCreativita::Preroll->value,
                'tipo' => TipoCreativita::Video->value,
            ])
            ->set('data.contenuto.nuovo', $video)
            ->assertSet('data.durata_secondi', 2)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, LiveCreative::sole()->durata_secondi);
    }

    #[Test]
    public function l_immagine_senza_testo_alternativo_non_si_salva(): void
    {
        Storage::fake('public');
        $this->actingAs($this->utente(UserRole::CommunicationManager));

        Livewire::test(CreateLiveCreative::class)
            ->fillForm([
                'sponsor_id' => Sponsor::factory()->create()->id,
                'nome' => 'Senza alt',
                'uso' => UsoCreativita::Preroll->value,
                'tipo' => TipoCreativita::Immagine->value,
                'durata_secondi' => 10,
                'testo_alternativo' => '',
            ])
            ->set('data.contenuto.nuovo', $this->png(1920, 1080))
            ->call('create')
            ->assertHasFormErrors(['testo_alternativo' => 'required']);
    }

    #[Test]
    public function chi_non_cura_gli_sponsor_non_vede_gli_spot_ne_l_interruttore(): void
    {
        $this->actingAs($this->utente(UserRole::ShopManager));

        $this->get('/admin/creativita-diretta')->assertForbidden();
        $this->get('/admin/settings/diretta')->assertForbidden();
    }

    #[Test]
    public function la_comunicazione_accende_l_interruttore(): void
    {
        $this->actingAs($this->utente(UserRole::CommunicationManager));

        $this->get('/admin/creativita-diretta')->assertSuccessful();

        Livewire::test(DirettaSettingsPage::class)
            ->assertSet('data.diretta_sponsor_abilitata', false)
            ->set('data.diretta_sponsor_abilitata', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(ImpostazioniDellaDiretta::abilitata());
        $this->assertSame('diretta', SiteSetting::query()->where('key', 'diretta_sponsor_abilitata')->value('group'));
    }

    #[Test]
    public function nell_elenco_lo_spot_video_non_finisce_in_un_immagine(): void
    {
        Storage::fake('public');
        $this->actingAs($this->utente(UserRole::CommunicationManager));
        $video = LiveCreative::factory()->video()->create();
        $video->addMedia(base_path('tests/Fixtures/Diretta/spot-1920x1080-2s.mp4'))->preservingOriginal()->toMediaCollection(LiveCreative::COLLEZIONE_CONTENUTO);

        $this->get('/admin/creativita-diretta')
            ->assertOk()
            ->assertDontSee('spot-1920x1080-2s.mp4', false);
    }

    #[Test]
    public function una_creativita_scaduta_o_spenta_non_e_valida(): void
    {
        Storage::fake('public');
        $creativita = LiveCreative::factory()->create(['valida_al' => now()->subDay()]);
        $creativita->addMedia($this->png(1920, 1080))->toMediaCollection(LiveCreative::COLLEZIONE_CONTENUTO);

        $this->assertFalse($creativita->valida());
        $this->assertSame(0, LiveCreative::valide()->count());

        $creativita->update(['valida_al' => null, 'attiva' => false]);
        $this->assertSame(0, LiveCreative::valide()->count());

        $creativita->update(['attiva' => true]);
        $this->assertSame(1, LiveCreative::valide()->count());
    }
}
