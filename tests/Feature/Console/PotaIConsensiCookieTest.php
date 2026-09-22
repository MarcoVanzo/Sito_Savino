<?php

namespace Tests\Feature\Console;

use App\Models\ConsensoCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `consensi:pota` è l'unica cosa che impedisce al registro dei consensi di
 * diventare quello che è fatto per evitare: una raccolta di dati che nessuno
 * ha più motivo di conservare.
 *
 * Gira dallo scheduler una volta a settimana e non lo guarda nessuno, quindi
 * i due modi in cui può sbagliare vanno provati qui: cancellare quello che
 * doveva tenere, o tenere quello che doveva cancellare.
 */
class PotaIConsensiCookieTest extends TestCase
{
    use RefreshDatabase;

    private function consensoDi(string $quando): ConsensoCookie
    {
        $consenso = ConsensoCookie::create([
            'riferimento' => ConsensoCookie::nuovoRiferimento(),
            'statistiche' => true,
            'marketing' => false,
            'azione' => 'concesso',
            'versione' => ConsensoCookie::VERSIONE,
        ]);

        // `created_at` sta fuori da `$fillable` e la colonna ha `useCurrent()`:
        // per invecchiare una riga si riscrive dopo averla creata.
        $consenso->forceFill(['created_at' => now()->parse($quando)])->save();

        return $consenso;
    }

    public function test_toglie_i_consensi_piu_vecchi_di_dodici_mesi(): void
    {
        $vecchio = $this->consensoDi(now()->subMonths(13)->toDateTimeString());
        $recente = $this->consensoDi(now()->subMonths(11)->toDateTimeString());

        $this->artisan('consensi:pota')
            ->expectsOutputToContain('1 consenso cancellato')
            ->assertSuccessful();

        $this->assertDatabaseMissing('consensi_cookie', ['id' => $vecchio->id]);
        $this->assertDatabaseHas('consensi_cookie', ['id' => $recente->id]);
    }

    public function test_il_confine_dei_dodici_mesi_tiene_il_consenso_di_ieri(): void
    {
        // Il giorno esatto del limite non si cancella: la prova del consenso
        // serve finché può essere richiesta, e un'ora di differenza non è un
        // motivo per non averla più.
        $sulFilo = $this->consensoDi(now()->subMonths(12)->addHour()->toDateTimeString());

        $this->artisan('consensi:pota')
            ->expectsOutputToContain('Nessun consenso da togliere')
            ->assertSuccessful();

        $this->assertDatabaseHas('consensi_cookie', ['id' => $sulFilo->id]);
    }

    public function test_con_mesi_si_accorcia_la_conservazione(): void
    {
        $treMesiFa = $this->consensoDi(now()->subMonths(3)->toDateTimeString());
        $ieri = $this->consensoDi(now()->subDay()->toDateTimeString());

        $this->artisan('consensi:pota', ['--mesi' => 2])->assertSuccessful();

        $this->assertDatabaseMissing('consensi_cookie', ['id' => $treMesiFa->id]);
        $this->assertDatabaseHas('consensi_cookie', ['id' => $ieri->id]);
    }

    public function test_un_mese_e_il_minimo_e_zero_non_svuota_il_registro(): void
    {
        // `--mesi=0` letto alla lettera vorrebbe dire "cancella tutto, anche il
        // consenso raccolto un minuto fa": una svista da riga di comando non
        // deve poter distruggere il registro.
        // Cinque giorni, non "adesso": un consenso dello stesso secondo in cui
        // gira il comando resterebbe comunque, e il test non distinguerebbe il
        // minimo di un mese da nessun minimo affatto.
        $diCinqueGiorniFa = $this->consensoDi(now()->subDays(5)->toDateTimeString());
        $vecchio = $this->consensoDi(now()->subMonths(2)->toDateTimeString());

        $this->artisan('consensi:pota', ['--mesi' => 0])->assertSuccessful();

        $this->assertDatabaseHas('consensi_cookie', ['id' => $diCinqueGiorniFa->id]);
        $this->assertDatabaseMissing('consensi_cookie', ['id' => $vecchio->id]);
    }

    public function test_su_un_registro_vuoto_non_ha_niente_da_dire(): void
    {
        $this->artisan('consensi:pota')
            ->expectsOutputToContain('Nessun consenso da togliere')
            ->assertSuccessful();
    }

    public function test_conta_al_plurale_quando_sono_piu_di_uno(): void
    {
        $this->consensoDi(now()->subMonths(14)->toDateTimeString());
        $this->consensoDi(now()->subMonths(15)->toDateTimeString());

        $this->artisan('consensi:pota')
            ->expectsOutputToContain('2 consensi cancellati')
            ->assertSuccessful();

        $this->assertSame(0, ConsensoCookie::count());
    }
}
