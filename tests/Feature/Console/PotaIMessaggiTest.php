<?php

namespace Tests\Feature\Console;

use App\Http\Controllers\PressAccreditationController;
use App\Models\ContactMessage;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * I ventiquattro mesi promessi dall'informativa.
 *
 * Prima di questo comando la promessa non la manteneva nessuno: i messaggi
 * restavano in archivio per sempre. Qui si verifica che il taglio cada dove
 * dice il testo pubblico e che non si porti via quello che è ancora dentro
 * il termine.
 */
class PotaIMessaggiTest extends TestCase
{
    use RefreshDatabase;

    private function messaggio(string $quando, array $attributi = []): ContactMessage
    {
        $messaggio = ContactMessage::create(array_merge([
            'name' => 'Tifoso',
            'email' => 'tifoso@example.test',
            'subject' => 'Informazioni',
            'message' => 'Buongiorno.',
        ], $attributi));

        // `created_at` non si passa alla create: il model lo riscrive.
        $messaggio->forceFill(['created_at' => $quando])->save();

        return $messaggio->refresh();
    }

    public function test_toglie_i_messaggi_oltre_i_ventiquattro_mesi(): void
    {
        $vecchio = $this->messaggio(now()->subMonths(25));
        $recente = $this->messaggio(now()->subMonths(23));

        $this->artisan('messaggi:pota')->assertSuccessful();

        $this->assertDatabaseMissing('contact_messages', ['id' => $vecchio->id]);
        $this->assertDatabaseHas('contact_messages', ['id' => $recente->id]);
    }

    public function test_porta_via_anche_le_richieste_di_accredito(): void
    {
        // L'accredito è un ContactMessage con un oggetto suo, e `extra_data`
        // tiene testata, ruolo, gara e telefono: sparisce con la riga.
        $accredito = $this->messaggio(now()->subMonths(30), [
            'subject' => PressAccreditationController::SUBJECT,
            'extra_data' => ['outlet' => 'Gazzetta', 'phone' => '333 1234567'],
        ]);

        $this->artisan('messaggi:pota')->assertSuccessful();

        $this->assertDatabaseMissing('contact_messages', ['id' => $accredito->id]);
    }

    public function test_conta_dalla_data_del_messaggio_non_dall_ultima_lettura(): void
    {
        // `updated_at` cambia quando la redazione segna "letto": contare da lì
        // rimanderebbe la scadenza dell'archivio a ogni giro nel pannello.
        $vecchio = $this->messaggio(now()->subMonths(36));
        $vecchio->update(['status' => 'read']);

        $this->artisan('messaggi:pota')->assertSuccessful();

        $this->assertDatabaseMissing('contact_messages', ['id' => $vecchio->id]);
    }

    public function test_la_prova_non_cancella_niente(): void
    {
        $vecchio = $this->messaggio(now()->subMonths(25));

        $this->artisan('messaggi:pota --prova')
            ->expectsOutputToContain('Da togliere: 1 messaggio')
            ->assertSuccessful();

        $this->assertDatabaseHas('contact_messages', ['id' => $vecchio->id]);
    }

    public function test_il_termine_si_puo_cambiare(): void
    {
        $messaggio = $this->messaggio(now()->subMonths(7));

        $this->artisan('messaggi:pota --mesi=6')->assertSuccessful();

        $this->assertDatabaseMissing('contact_messages', ['id' => $messaggio->id]);
    }

    public function test_senza_niente_da_togliere_non_si_lamenta(): void
    {
        $this->messaggio(now()->subMonths(2));

        $this->artisan('messaggi:pota')
            ->expectsOutputToContain('Nessun messaggio da togliere.')
            ->assertSuccessful();
    }

    /**
     * La promessa dell'informativa vive nello scheduler: se il comando non è
     * pianificato, i ventiquattro mesi tornano a essere una buona intenzione.
     */
    public function test_e_pianificato_una_volta_sola(): void
    {
        $eventi = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (Event $evento): bool => str_contains($evento->command ?? '', 'messaggi:pota'),
        ));

        $this->assertCount(1, $eventi, 'la potatura dei messaggi va schedulata una volta sola');
        $this->assertSame('0 0 * * 0', $eventi[0]->expression, 'settimanale, come il registro dei consensi');
    }
}
