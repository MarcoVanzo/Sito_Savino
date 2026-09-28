<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\Settings\HomepageSettingsPage;
use App\Filament\Resources\EventoResource\Pages\CreateEvento;
use App\Filament\Resources\EventoResource\Pages\ListEventi;
use App\Models\Evento;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\MatchDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventiEMatchDayNelPannelloTest extends TestCase
{
    use RefreshDatabase;

    private function utente(UserRole $ruolo): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $ruolo, 'is_active' => true])->save();

        return $user->refresh();
    }

    #[Test]
    public function la_redazione_crea_un_evento_dal_pannello(): void
    {
        $this->actingAs($this->utente(UserRole::CommunicationManager));

        Livewire::test(ListEventi::class)->assertSuccessful();

        Livewire::test(CreateEvento::class)
            ->fillForm([
                'titolo' => 'Presentazione della squadra',
                'inizia_il' => '2026-10-10 18:00',
                'luogo' => 'Palazzetto di Scandicci',
                'link' => '/biglietteria',
                'pubblicato' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $evento = Evento::sole();
        $this->assertSame('Presentazione della squadra', $evento->getTranslation('titolo', 'it'));
        $this->assertSame('2026-10-10 18:00', $evento->inizia_il->format('Y-m-d H:i'));
    }

    #[Test]
    public function la_fine_non_puo_venire_prima_dell_inizio(): void
    {
        $this->actingAs($this->utente(UserRole::SuperAdmin));

        Livewire::test(CreateEvento::class)
            ->fillForm([
                'titolo' => 'Festa',
                'inizia_il' => '2026-10-10 18:00',
                'finisce_il' => '2026-10-10 17:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['finisce_il']);
    }

    #[Test]
    public function la_modalita_match_day_si_salva_dalle_impostazioni_della_homepage(): void
    {
        Livewire::actingAs($this->utente(UserRole::SuperAdmin))
            ->test(HomepageSettingsPage::class)
            ->assertSet('data.match_day_modalita', MatchDay::AUTO)
            ->assertSet('data.match_day_popup_titolo.en', 'Match day!')
            ->set('data.match_day_modalita', MatchDay::ACCESO)
            ->set('data.match_day_popup_url', 'https://www.vivaticket.com/it/ticket/savino')
            ->call('save')
            ->assertHasNoErrors();

        // «Accesa» vale per oggi: la data la scrive il salvataggio.
        $this->assertSame(now()->toDateString(), SiteSetting::get('match_day_acceso_il'));
        $this->assertSame(MatchDay::ACCESO, MatchDay::modalita());
        // Le chiavi restano nel gruppo `home`, l'unico che arriva al sito.
        $this->assertSame('home', SiteSetting::where('key', 'match_day_popup_url')->value('group'));
        $this->assertSame(
            ['it' => 'Oggi si gioca!', 'en' => 'Match day!'],
            SiteSetting::perLocale('match_day_popup_titolo'),
        );
        $this->assertSame('https://www.vivaticket.com/it/ticket/savino', MatchDay::stato()['popup']['url']);
    }

    #[Test]
    public function il_giorno_dopo_il_modulo_dice_automatica(): void
    {
        SiteSetting::set('match_day_modalita', MatchDay::ACCESO, 'home');
        SiteSetting::set('match_day_acceso_il', now()->subDay()->toDateString(), 'home');

        Livewire::actingAs($this->utente(UserRole::SuperAdmin))
            ->test(HomepageSettingsPage::class)
            ->assertSet('data.match_day_modalita', MatchDay::AUTO)
            ->assertSee('Spento: oggi la homepage è quella di sempre.');
    }
}
