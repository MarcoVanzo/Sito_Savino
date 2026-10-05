<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PressAccreditationResource;
use App\Filament\Resources\PressAccreditationResource\Pages\ManagePressAccreditations;
use App\Http\Controllers\PressAccreditationController;
use App\Mail\AccreditoStampaConfermato;
use App\Models\ContactMessage;
use App\Models\Game;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class PressAccreditationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array<string, string>
     */
    private function richiestaValida(array $sovrascritture = []): array
    {
        return array_merge([
            'first_name' => 'Chiara',
            'last_name' => 'Bianchi',
            'email' => 'chiara@testata.it',
            'phone' => '055 1234567',
            'outlet' => 'Il Tirreno',
            'role' => 'fotografo',
            'match' => 'Savino Del Bene Volley — Numia Vero Volley Milano',
            'notes' => 'Servono due pass per il fotografo e l’assistente.',
            'honeypot' => '',
        ], $sovrascritture);
    }

    public function test_una_richiesta_valida_viene_registrata(): void
    {
        Mail::fake();

        $risposta = $this->post(route('comunicazione.accrediti.submit'), $this->richiestaValida());

        $risposta->assertRedirect();
        $risposta->assertSessionHas('success');

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Chiara Bianchi',
            'email' => 'chiara@testata.it',
            'status' => 'unread',
        ]);
    }

    /**
     * L'oggetto è l'unico legame fra il modulo pubblico e l'elenco "Richieste
     * Accrediti" del pannello: se cambia da una parte sola, la redazione vede
     * un elenco vuoto senza che nulla vada in errore.
     */
    public function test_la_richiesta_compare_nell_elenco_del_pannello(): void
    {
        Mail::fake();

        $this->post(route('comunicazione.accrediti.submit'), $this->richiestaValida());

        $this->assertSame(
            PressAccreditationController::SUBJECT,
            ContactMessage::first()->subject,
        );

        $this->assertSame(1, PressAccreditationResource::getEloquentQuery()->count());
    }

    public function test_testata_ruolo_e_gara_restano_leggibili(): void
    {
        Mail::fake();

        $this->post(route('comunicazione.accrediti.submit'), $this->richiestaValida());

        $messaggio = ContactMessage::first();

        $this->assertSame('Il Tirreno', $messaggio->extra_data['outlet']);
        $this->assertSame('fotografo', $messaggio->extra_data['role']);
        $this->assertStringContainsString('Il Tirreno', $messaggio->message);
        $this->assertStringContainsString('Numia Vero Volley Milano', $messaggio->message);
    }

    /**
     * La richiesta parte verso l'indirizzo dell'ufficio stampa configurato in
     * Impostazioni -> Contatti, non verso il mittente di sistema.
     *
     * `Mail::raw()` non produce un Mailable, quindi non lo si conta con
     * `Mail::assertSentCount`: qui si guarda il messaggio vero nel trasporto
     * di prova (`array`).
     */
    public function test_la_richiesta_viene_spedita_all_ufficio_stampa(): void
    {
        SiteSetting::updateOrCreate(
            ['group' => 'contact', 'key' => 'press_email'],
            ['value' => 'press@savinodelbenevolley.it', 'type' => 'text'],
        );

        Cache::flush();

        $this->post(route('comunicazione.accrediti.submit'), $this->richiestaValida());

        $spediti = Mail::getSymfonyTransport()->messages();

        $this->assertCount(1, $spediti);

        $messaggio = $spediti[0]->getOriginalMessage();

        $this->assertSame('press@savinodelbenevolley.it', $messaggio->getTo()[0]->getAddress());
        $this->assertStringContainsString('Il Tirreno', $messaggio->getSubject());
        $this->assertStringContainsString('chiara@testata.it', $messaggio->getReplyTo()[0]->getAddress());
    }

    public function test_i_campi_obbligatori_sono_richiesti(): void
    {
        Mail::fake();

        $risposta = $this->post(route('comunicazione.accrediti.submit'), $this->richiestaValida([
            'outlet' => '',
            'match' => '',
        ]));

        $risposta->assertSessionHasErrors(['outlet', 'match']);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_un_ruolo_inventato_viene_rifiutato(): void
    {
        Mail::fake();

        $risposta = $this->post(route('comunicazione.accrediti.submit'), $this->richiestaValida([
            'role' => 'presidente',
        ]));

        $risposta->assertSessionHasErrors('role');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    /**
     * Il campo trappola è invisibile in pagina: se arriva compilato, chi ha
     * inviato non stava leggendo. Si finge successo per non spiegare al bot
     * come aggirare il controllo.
     */
    public function test_il_campo_trappola_scarta_la_richiesta_senza_dirlo(): void
    {
        Mail::fake();

        $risposta = $this->post(route('comunicazione.accrediti.submit'), $this->richiestaValida([
            'honeypot' => 'https://spam.example',
        ]));

        $risposta->assertRedirect();
        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingSent();
    }

    /**
     * La tendina della gara mostra la data per esteso nella lingua della
     * pagina. La componeva `Carbon::translatedFormat()`, che sotto php-fpm
     * faceva morire il processo: ora i mesi vengono da `site.months`, e questo
     * test è ciò che impedisce di tornare indietro senza accorgersene.
     */
    public function test_la_tendina_della_gara_scrive_la_data_per_esteso(): void
    {
        $casa = Team::factory()->internal()->create(['name' => 'Savino Del Bene Volley']);
        $ospite = Team::factory()->create(['name' => 'Numia Vero Volley Milano']);

        Game::factory()->create([
            'home_team_id' => $casa->id,
            'away_team_id' => $ospite->id,
            'match_date' => now()->addDays(10)->setDate(2027, 3, 4)->setTime(20, 30),
            'status' => GameStatus::Scheduled,
        ]);

        Page::factory()->create([
            'slug' => 'accrediti-stampa',
            'template' => 'Public/Comunicazione',
            'status' => PostStatus::Published,
        ]);

        $sfida = 'Savino Del Bene Volley — Numia Vero Volley Milano';

        $this->get(route('comunicazione.page', ['slug' => 'accrediti-stampa']))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('upcomingHomeGames', [[
                'value' => $sfida,
                'label' => $sfida.' · 4 marzo 2027',
            ]]));

        Cache::flush();

        $this->get(route('en.comunicazione.page', ['slug' => 'accrediti-stampa']))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('upcomingHomeGames', [[
                'value' => $sfida,
                'label' => $sfida.' · 4 March 2027',
            ]]));
    }

    private function amministratore(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        return $user->refresh();
    }

    /**
     * Segnalazione della redazione del 05/10/2026: accreditata una richiesta
     * di prova, al richiedente non arrivava niente. Il pulsante cambiava solo
     * lo stato.
     */
    public function test_accreditare_manda_la_conferma_al_richiedente(): void
    {
        Mail::fake();

        $this->post(route('comunicazione.accrediti.submit'), $this->richiestaValida());
        $richiesta = ContactMessage::firstOrFail();

        Livewire::actingAs($this->amministratore())
            ->test(ManagePressAccreditations::class)
            ->callTableAction('markAsReplied', $richiesta, ['messaggio' => 'Pass al cancello 3 dalle 19.'])
            ->assertHasNoTableActionErrors();

        Mail::assertSent(AccreditoStampaConfermato::class, fn (AccreditoStampaConfermato $mail) => $mail->hasTo('chiara@testata.it')
            && $mail->messaggio === 'Pass al cancello 3 dalle 19.');

        $richiesta->refresh();
        $this->assertSame('replied', $richiesta->status);
        $this->assertNotEmpty($richiesta->extra_data['conferma_inviata_il']);
        // Quello che il sito aveva raccolto resta.
        $this->assertSame('Il Tirreno', $richiesta->extra_data['outlet']);
    }

    public function test_la_conferma_dice_gara_testata_e_messaggio_nella_lingua_della_richiesta(): void
    {
        $richiesta = ContactMessage::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => PressAccreditationController::SUBJECT,
            'message' => '-',
            'status' => 'unread',
            'extra_data' => ['outlet' => 'Volleyball World', 'role' => 'fotografo', 'match' => 'Savino — Milano', 'lingua' => 'en'],
        ]);

        $html = (new AccreditoStampaConfermato($richiesta, 'Gate 3 <b>from</b> 7pm'))->render();

        $this->assertStringContainsString('Your accreditation is confirmed', $html);
        $this->assertStringContainsString('Volleyball World', $html);
        $this->assertStringContainsString('Savino — Milano', $html);
        $this->assertStringContainsString('Photographer', $html);
        // Il messaggio della redazione è testo, non HTML.
        $this->assertStringContainsString('Gate 3 &lt;b&gt;from&lt;/b&gt; 7pm', $html);
    }

    public function test_la_richiesta_ricorda_la_lingua_per_la_conferma(): void
    {
        Mail::fake();

        $this->post(route('comunicazione.accrediti.submit'), $this->richiestaValida());

        $this->assertSame('it', ContactMessage::firstOrFail()->extra_data['lingua']);
    }

    public function test_se_l_email_non_parte_resta_accreditata_e_la_conferma_non_risulta_inviata(): void
    {
        Mail::fake();
        $this->post(route('comunicazione.accrediti.submit'), $this->richiestaValida());
        $richiesta = ContactMessage::firstOrFail();

        // Come farebbe Resend giù.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Resend non risponde'));

        Livewire::actingAs($this->amministratore())
            ->test(ManagePressAccreditations::class)
            ->callTableAction('markAsReplied', $richiesta, ['messaggio' => null])
            ->assertNotified('Accreditato, ma l\'email non è partita');

        $richiesta->refresh();
        $this->assertSame('replied', $richiesta->status);
        $this->assertArrayNotHasKey('conferma_inviata_il', $richiesta->extra_data);
    }

    public function test_la_scheda_dice_quando_e_partita_la_conferma(): void
    {
        $richiesta = ContactMessage::create([
            'name' => 'Chiara Bianchi',
            'email' => 'chiara@testata.it',
            'subject' => PressAccreditationController::SUBJECT,
            'message' => '-',
            'status' => 'replied',
            'extra_data' => ['outlet' => 'Il Tirreno', 'conferma_inviata_il' => '05/10/2026 13:10'],
        ]);

        Livewire::actingAs($this->amministratore())
            ->test(ManagePressAccreditations::class)
            ->assertTableActionHasLabel('markAsReplied', 'Reinvia conferma', $richiesta)
            ->mountTableAction('edit', $richiesta)
            ->assertSee('05/10/2026 13:10')
            ->assertSee('Da qui non parte nessuna email');
    }
}
