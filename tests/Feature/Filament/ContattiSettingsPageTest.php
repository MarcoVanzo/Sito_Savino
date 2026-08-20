<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\Settings\ContattiSettingsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gli orari di apertura sono l'unico recapito fatto di parole, quindi l'unico
 * tradotto: su /en/contacts si leggeva "Lun-Ven" sotto l'etichetta inglese.
 *
 * La pagina delle impostazioni carica i valori già risolti nella lingua del
 * pannello: senza un campo per lingua, salvare riscriverebbe l'impostazione
 * come testo semplice e la traduzione inglese sparirebbe al primo salvataggio.
 */
class ContattiSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function gli_orari_si_modificano_in_tutte_e_due_le_lingue(): void
    {
        SiteSetting::set('office_hours', ['it' => 'Lun-Ven: 09:00-18:00', 'en' => 'Mon-Fri: 09:00-18:00']);

        Livewire::actingAs($this->superAdmin())
            ->test(ContattiSettingsPage::class)
            ->assertSuccessful()
            ->assertSet('data.office_hours.it', 'Lun-Ven: 09:00-18:00')
            ->assertSet('data.office_hours.en', 'Mon-Fri: 09:00-18:00')
            ->set('data.office_hours.it', 'Lun-Ven: 09:00-17:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            ['it' => 'Lun-Ven: 09:00-17:00', 'en' => 'Mon-Fri: 09:00-18:00'],
            SiteSetting::perLocale('office_hours'),
        );

        // Una riga sola per l'impostazione, non una per lingua.
        $this->assertSame(1, SiteSetting::query()->where('key', 'like', 'office_hours%')->count());
    }

    /**
     * Un'installazione che non ha ancora fatto girare la migrazione ha il valore
     * in testo semplice: deve comparire sulla prima lingua, non sparire.
     */
    #[Test]
    public function un_valore_non_ancora_tradotto_finisce_sulla_lingua_principale(): void
    {
        SiteSetting::set('office_hours', 'Lun-Ven: 09:00-18:00');

        $this->assertSame(
            ['it' => 'Lun-Ven: 09:00-18:00', 'en' => ''],
            SiteSetting::perLocale('office_hours'),
        );
    }

    #[Test]
    public function il_sito_legge_gli_orari_nella_lingua_in_uso(): void
    {
        SiteSetting::set('office_hours', ['it' => 'Lun-Ven: 09:00-18:00', 'en' => 'Mon-Fri: 09:00-18:00']);

        app()->setLocale('en');
        SiteSetting::clearCache();

        $this->assertSame('Mon-Fri: 09:00-18:00', SiteSetting::getGroup('contact')['office_hours']);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user->refresh();
    }
}
