<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\PostStatus;
use App\Filament\Resources\PressAccreditationResource;
use App\Http\Controllers\PressAccreditationController;
use App\Models\ContactMessage;
use App\Models\Game;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
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
}
