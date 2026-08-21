<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\Settings\LegalSettingsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I documenti legali caricati dal pannello devono arrivare al sito.
 *
 * I campi si chiamano `legal.privacy_policy`, ma il salvataggio scriveva la
 * riga con quel nome intero come chiave e senza gruppo. Il sito legge le
 * impostazioni raggruppate, quindi non le trovava mai: nel footer i documenti
 * di governance restavano link vuoti, e riaprendo la pagina i campi erano di
 * nuovo bianchi.
 */
class LegalSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function redattore(): User
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        return $utente->refresh();
    }

    /** La chiave si conserva intera: è la convenzione di `shop.*` e `lvf.*`. */
    #[Test]
    public function la_chiave_si_conserva_com_e_scritta(): void
    {
        SiteSetting::set('legal.privacy_policy', 'legal/privacy.pdf');

        $this->assertDatabaseHas('site_settings', [
            'key' => 'legal.privacy_policy',
            'value' => 'legal/privacy.pdf',
        ]);
    }

    /** Il sito legge le impostazioni raggruppate: è lì che devono comparire. */
    #[Test]
    public function il_documento_arriva_al_sito_sotto_il_suo_gruppo(): void
    {
        SiteSetting::set('legal.modello_organizzativo', 'legal/modello.pdf');
        Cache::flush();

        $pubbliche = SiteSetting::getPublicGrouped();

        $this->assertSame('legal/modello.pdf', $pubbliche['legal']['modello_organizzativo'] ?? null);
    }

    /**
     * Una chiave salvata col gruppo dentro si rilegge anche scrivendo il
     * gruppo: prima `get('legal.privacy_policy')` funzionava solo per caso e
     * `get('contact.press_email')` restituiva sempre null.
     */
    #[Test]
    public function si_rilegge_scrivendo_il_gruppo(): void
    {
        SiteSetting::set('legal.cookie_policy', 'legal/cookie.pdf');
        Cache::flush();

        $this->assertSame('legal/cookie.pdf', SiteSetting::get('legal.cookie_policy'));
    }

    /**
     * Il giro completo: si salva dal pannello, si riapre la pagina e i campi
     * sono ancora pieni.
     */
    #[Test]
    public function il_modulo_ritrova_quello_che_ha_salvato(): void
    {
        $this->actingAs($this->redattore());

        SiteSetting::set('legal.protocollo_bullismo', 'legal/bullismo.pdf');
        Cache::flush();

        Livewire::test(LegalSettingsPage::class)
            ->assertSet('data.legal.protocollo_bullismo', 'legal/bullismo.pdf');
    }

    /** Salvare i documenti legali non deve toccare le altre impostazioni. */
    #[Test]
    public function salvare_non_tocca_le_altre_impostazioni(): void
    {
        $this->actingAs($this->redattore());

        SiteSetting::set('hero_title', 'SAVINO DEL BENE', 'home');
        Cache::flush();

        Livewire::test(LegalSettingsPage::class)->call('save');
        Cache::flush();

        $this->assertSame('SAVINO DEL BENE', SiteSetting::get('hero_title'));
        $this->assertDatabaseHas('site_settings', ['key' => 'hero_title', 'group' => 'home']);
    }
}
