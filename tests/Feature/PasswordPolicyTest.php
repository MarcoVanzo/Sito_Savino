<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use App\Notifications\PasswordExpiringSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function staff(array $attributes = []): User
    {
        $user = User::factory()->create();
        $user->forceFill(array_merge([
            'role' => UserRole::SuperAdmin,
            'must_change_password' => false,
            'is_active' => true,
        ], $attributes))->save();

        return $user->fresh();
    }

    // --- Storico e divieto di riuso ---

    public function test_la_password_iniziale_finisce_nello_storico(): void
    {
        $user = $this->staff();

        $this->assertSame(1, $user->passwordHistories()->count());
    }

    public function test_non_si_puo_riusare_la_password_attuale(): void
    {
        $user = $this->staff();

        $response = $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_non_si_possono_riusare_le_ultime_sei_password(): void
    {
        $user = $this->staff();
        $prima = 'PrimaPassword!2026';

        // Cambia password 5 volte: storico = iniziale + 5 = 6 voci.
        $corrente = 'password';
        foreach ([$prima, 'Seconda!2026aaa', 'Terza!2026bbbb', 'Quarta!2026cccc', 'Quinta!2026dddd'] as $nuova) {
            $this->actingAs($user->fresh())->put('/password', [
                'current_password' => $corrente,
                'password' => $nuova,
                'password_confirmation' => $nuova,
            ])->assertSessionHasNoErrors();
            $corrente = $nuova;
        }

        $this->assertSame(6, $user->fresh()->passwordHistories()->count());

        // La prima password è ancora nelle ultime 6: deve essere rifiutata.
        $this->actingAs($user->fresh())->put('/password', [
            'current_password' => $corrente,
            'password' => $prima,
            'password_confirmation' => $prima,
        ])->assertSessionHasErrors('password');
    }

    public function test_lo_storico_non_supera_la_dimensione_configurata(): void
    {
        $user = $this->staff();

        $corrente = 'password';
        foreach (['Uno!2026aaaaaa', 'Due!2026bbbbbb', 'Tre!2026cccccc', 'Quattro!2026dd', 'Cinque!2026eee', 'Sei!2026ffffff'] as $nuova) {
            $this->actingAs($user->fresh())->put('/password', [
                'current_password' => $corrente,
                'password' => $nuova,
                'password_confirmation' => $nuova,
            ])->assertSessionHasNoErrors();
            $corrente = $nuova;
        }

        $this->assertSame(
            config('password_policy.history_size'),
            $user->fresh()->passwordHistories()->count()
        );
    }

    public function test_una_password_uscita_dallo_storico_torna_riusabile(): void
    {
        $user = $this->staff();

        // Password di partenza conforme alla policy, così il riuso finale non
        // viene respinto per un motivo diverso (robustezza) da quello in esame.
        $iniziale = 'Vecchia!2026xyz';
        $this->actingAs($user->fresh())->put('/password', [
            'current_password' => 'password',
            'password' => $iniziale,
            'password_confirmation' => $iniziale,
        ])->assertSessionHasNoErrors();

        // 6 cambi successivi: $iniziale esce dalla finestra delle ultime 6.
        $corrente = $iniziale;
        foreach (['Uno!2026aaaaaa', 'Due!2026bbbbbb', 'Tre!2026cccccc', 'Quattro!2026dd', 'Cinque!2026eee', 'Sei!2026ffffff'] as $nuova) {
            $this->actingAs($user->fresh())->put('/password', [
                'current_password' => $corrente,
                'password' => $nuova,
                'password_confirmation' => $nuova,
            ])->assertSessionHasNoErrors();
            $corrente = $nuova;
        }

        $this->actingAs($user->fresh())->put('/password', [
            'current_password' => $corrente,
            'password' => $iniziale,
            'password_confirmation' => $iniziale,
        ])->assertSessionHasNoErrors();
    }

    // --- Robustezza ---

    public function test_le_password_deboli_sono_rifiutate(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'segreto1',
            'password_confirmation' => 'segreto1',
        ])->assertSessionHasErrors('password');
    }

    // --- Scadenza ---

    public function test_la_password_scade_dopo_il_periodo_configurato(): void
    {
        $mesi = (int) config('password_policy.expires_after_months');

        $user = $this->staff(['password_changed_at' => now()->subMonths($mesi)->subDay()]);

        $this->assertTrue($user->passwordHasExpired());

        // L'utente viene dirottato sul cambio password anche senza must_change_password.
        $this->actingAs($user)->get('/admin')->assertRedirect(route('password.change'));
    }

    public function test_una_password_recente_non_e_scaduta(): void
    {
        $user = $this->staff(['password_changed_at' => now()->subMonth()]);

        $this->assertFalse($user->passwordHasExpired());
        $this->assertFalse($user->passwordIsExpiringSoon());
        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_il_preavviso_scatta_nella_finestra_configurata(): void
    {
        $mesi = (int) config('password_policy.expires_after_months');
        $giorni = (int) config('password_policy.warn_before_days');

        $user = $this->staff([
            'password_changed_at' => now()->subMonths($mesi)->addDays($giorni - 1),
        ]);

        $this->assertTrue($user->passwordIsExpiringSoon());
        $this->assertLessThanOrEqual($giorni, $user->daysUntilPasswordExpires());
    }

    public function test_il_cambio_password_azzera_scadenza_e_preavviso(): void
    {
        $mesi = (int) config('password_policy.expires_after_months');

        $user = $this->staff([
            'password_changed_at' => now()->subMonths($mesi)->addDays(2),
            'password_expiry_notified_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'PasswordNuova!2026',
            'password_confirmation' => 'PasswordNuova!2026',
        ])->assertSessionHasNoErrors();

        $fresh = $user->fresh();

        $this->assertFalse($fresh->passwordIsExpiringSoon());
        $this->assertNull($fresh->password_expiry_notified_at);
        $this->assertTrue($fresh->password_changed_at->isToday());
    }

    // --- Preavviso via email ---

    public function test_al_login_parte_una_sola_email_di_preavviso(): void
    {
        Notification::fake();

        $mesi = (int) config('password_policy.expires_after_months');
        $user = $this->staff([
            'password_changed_at' => now()->subMonths($mesi)->addDays(3),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        Notification::assertSentToTimes($user, PasswordExpiringSoon::class, 1);

        // Un secondo login non deve rimandarla.
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        Notification::assertSentToTimes($user, PasswordExpiringSoon::class, 1);
    }

    public function test_nessuna_email_se_la_password_non_sta_scadendo(): void
    {
        Notification::fake();

        $user = $this->staff(['password_changed_at' => now()]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        Notification::assertNotSentTo($user, PasswordExpiringSoon::class);
    }

    // --- Creazione/modifica utenti dal pannello ---

    public function test_il_pannello_rifiuta_una_password_debole_alla_creazione(): void
    {
        $admin = $this->staff();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Nuovo Staff',
                'email' => 'nuovo.staff@savinodelbene.it',
                'role' => UserRole::CommunicationManager->value,
                'password' => 'debole',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'nuovo.staff@savinodelbene.it']);
    }

    public function test_il_pannello_rifiuta_il_riuso_di_una_password_gia_usata(): void
    {
        $admin = $this->staff();
        $target = $this->staff(['role' => UserRole::ShopManager]);

        // Password robusta (supera Password::defaults) ma già presente nello
        // storico: deve essere respinta lo stesso.
        $target->update(['password' => 'Vecchia!Password2026']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['password' => 'Vecchia!Password2026'])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }
}
