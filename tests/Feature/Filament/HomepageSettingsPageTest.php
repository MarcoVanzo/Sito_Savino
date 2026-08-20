<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\Settings\HomepageSettingsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I testi della homepage sono in archivio come JSON per lingua, ma la pagina
 * delle impostazioni li caricava risolti nella lingua del pannello e li
 * riscriveva come testo semplice: bastava aprire Impostazioni → Homepage e
 * premere Salva perché l'inglese sparisse da claim, pulsanti, banner e numeri.
 *
 * Il salvataggio senza modifiche è il caso che conta: è quello che capita
 * davvero, e prima faceva danno senza che nessuno toccasse un campo.
 */
class HomepageSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function salvare_senza_modifiche_non_cancella_le_traduzioni(): void
    {
        SiteSetting::set('hero_tagline', ['it' => 'Scatena la Potenza.', 'en' => 'Unleash the Power.']);
        SiteSetting::set('stats', [
            'it' => [['value' => '40+', 'label' => 'Anni di Storia', 'icon' => '🏆']],
            'en' => [['value' => '40+', 'label' => 'Years of History', 'icon' => '🏆']],
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(HomepageSettingsPage::class)
            ->assertSuccessful()
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            ['it' => 'Scatena la Potenza.', 'en' => 'Unleash the Power.'],
            SiteSetting::perLocale('hero_tagline'),
        );

        $numeri = SiteSetting::perLocale('stats');
        $this->assertSame('Anni di Storia', $numeri['it'][0]['label']);
        $this->assertSame('Years of History', $numeri['en'][0]['label']);
    }

    #[Test]
    public function le_due_lingue_si_modificano_dal_pannello(): void
    {
        SiteSetting::set('cta_shop_title', ['it' => 'Shop Ufficiale', 'en' => 'Official Shop']);

        Livewire::actingAs($this->superAdmin())
            ->test(HomepageSettingsPage::class)
            ->assertSet('data.cta_shop_title.en', 'Official Shop')
            ->set('data.cta_shop_title.en', 'Team Store')
            ->call('save');

        $this->assertSame(
            ['it' => 'Shop Ufficiale', 'en' => 'Team Store'],
            SiteSetting::perLocale('cta_shop_title'),
        );
    }

    /**
     * Il front-end deve continuare a ricevere l'elenco dei numeri, non il
     * contenitore per lingua: è il motivo per cui `resolveForLocale()` risolve
     * anche i valori già decodificati.
     */
    #[Test]
    public function il_sito_riceve_i_numeri_della_lingua_in_uso(): void
    {
        SiteSetting::set('stats', [
            'it' => [['value' => '40+', 'label' => 'Anni di Storia', 'icon' => '🏆']],
            'en' => [['value' => '40+', 'label' => 'Years of History', 'icon' => '🏆']],
        ]);

        app()->setLocale('en');
        SiteSetting::clearCache();

        $numeri = SiteSetting::getGroup('home')['stats'];

        $this->assertSame('Years of History', $numeri[0]['label']);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user->refresh();
    }
}
