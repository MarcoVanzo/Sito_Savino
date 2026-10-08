<?php

namespace Tests\Feature\Console;

use App\Models\ConsensoCookie;
use App\Services\CatenaDeiConsensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `consensi:pota` è l'unica cosa che impedisce al registro dei consensi di
 * diventare quello che è fatto per evitare: una raccolta di dati che nessuno
 * ha più motivo di conservare.
 *
 * Conserva ventiquattro mesi: i dodici in cui il consenso vale più dodici per
 * le contestazioni.
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
        // Il registro si scrive solo in coda alla catena, con l'ora del
        // momento: per invecchiare una riga si sposta l'orologio.
        $this->travelTo(now()->parse($quando));

        $consenso = CatenaDeiConsensi::registra([
            'riferimento' => ConsensoCookie::nuovoRiferimento(),
            'statistiche' => true,
            'marketing' => false,
            'azione' => 'concesso',
            'versione' => ConsensoCookie::VERSIONE,
        ]);

        $this->travelBack();

        return $consenso;
    }

    #[Test]
    public function toglie_i_consensi_piu_vecchi_di_ventiquattro_mesi(): void
    {
        $vecchio = $this->consensoDi(now()->subMonths(25)->toDateTimeString());
        // Scaduto come consenso (più di dodici mesi) ma ancora dentro il
        // margine per le contestazioni: resta.
        $recente = $this->consensoDi(now()->subMonths(13)->toDateTimeString());

        $this->artisan('consensi:pota')
            ->expectsOutputToContain('1 consenso cancellato')
            ->assertSuccessful();

        $this->assertDatabaseMissing('consensi_cookie', ['id' => $vecchio->id]);
        $this->assertDatabaseHas('consensi_cookie', ['id' => $recente->id]);
    }

    #[Test]
    public function il_confine_dei_ventiquattro_mesi_tiene_il_consenso_sul_filo(): void
    {
        // Il giorno esatto del limite non si cancella: la prova del consenso
        // serve finché può essere richiesta, e un'ora di differenza non è un
        // motivo per non averla più.
        $sulFilo = $this->consensoDi(now()->subMonths(24)->addHour()->toDateTimeString());

        $this->artisan('consensi:pota')
            ->expectsOutputToContain('Nessun consenso da togliere')
            ->assertSuccessful();

        $this->assertDatabaseHas('consensi_cookie', ['id' => $sulFilo->id]);
    }

    #[Test]
    public function con_mesi_si_accorcia_la_conservazione(): void
    {
        $treMesiFa = $this->consensoDi(now()->subMonths(3)->toDateTimeString());
        $ieri = $this->consensoDi(now()->subDay()->toDateTimeString());

        $this->artisan('consensi:pota', ['--mesi' => 2])->assertSuccessful();

        $this->assertDatabaseMissing('consensi_cookie', ['id' => $treMesiFa->id]);
        $this->assertDatabaseHas('consensi_cookie', ['id' => $ieri->id]);
    }

    #[Test]
    public function un_mese_e_il_minimo_e_zero_non_svuota_il_registro(): void
    {
        // `--mesi=0` letto alla lettera vorrebbe dire "cancella tutto, anche il
        // consenso raccolto un minuto fa": una svista da riga di comando non
        // deve poter distruggere il registro.
        // Cinque giorni, non "adesso": un consenso dello stesso secondo in cui
        // gira il comando resterebbe comunque, e il test non distinguerebbe il
        // minimo di un mese da nessun minimo affatto.
        // In ordine di tempo, come arrivano davvero: la potatura toglie la
        // testa del registro per id.
        $vecchio = $this->consensoDi(now()->subMonths(2)->toDateTimeString());
        $diCinqueGiorniFa = $this->consensoDi(now()->subDays(5)->toDateTimeString());

        $this->artisan('consensi:pota', ['--mesi' => 0])->assertSuccessful();

        $this->assertDatabaseHas('consensi_cookie', ['id' => $diCinqueGiorniFa->id]);
        $this->assertDatabaseMissing('consensi_cookie', ['id' => $vecchio->id]);
    }

    #[Test]
    public function su_un_registro_vuoto_non_ha_niente_da_dire(): void
    {
        $this->artisan('consensi:pota')
            ->expectsOutputToContain('Nessun consenso da togliere')
            ->assertSuccessful();
    }

    #[Test]
    public function conta_al_plurale_quando_sono_piu_di_uno(): void
    {
        $this->consensoDi(now()->subMonths(26)->toDateTimeString());
        $this->consensoDi(now()->subMonths(25)->toDateTimeString());

        $this->artisan('consensi:pota')
            ->expectsOutputToContain('2 consensi cancellati')
            ->assertSuccessful();

        $this->assertSame(0, ConsensoCookie::count());
    }

    #[Test]
    public function dopo_la_potatura_la_catena_resta_verificabile_dall_ancora(): void
    {
        $tolto = $this->consensoDi(now()->subMonths(26)->toDateTimeString());
        $this->consensoDi(now()->subMonths(2)->toDateTimeString());
        $this->consensoDi(now()->subDay()->toDateTimeString());

        $this->artisan('consensi:pota')->assertSuccessful();

        // La potatura lascia l'impronta dell'ultima riga tolta: la prima
        // rimasta vi si aggancia, e la verifica riparte da lì.
        $this->assertDatabaseHas('consensi_cookie_potature', [
            'fino_a_id' => $tolto->id,
            'righe' => 1,
            'ultima_impronta' => $tolto->impronta_riga,
        ]);
        $this->assertSame(2, ConsensoCookie::count());
        $this->assertNull(CatenaDeiConsensi::verifica()['guasto']);

        // Un consenso nuovo dopo la potatura continua la stessa catena.
        $this->consensoDi(now()->toDateTimeString());
        $this->assertNull(CatenaDeiConsensi::verifica()['guasto']);
    }

    #[Test]
    public function potare_tutto_il_registro_lascia_l_ancora_al_consenso_successivo(): void
    {
        $tolto = $this->consensoDi(now()->subMonths(30)->toDateTimeString());

        $this->artisan('consensi:pota')->assertSuccessful();
        $this->assertSame(0, ConsensoCookie::count());

        $nuovo = $this->consensoDi(now()->toDateTimeString());

        $this->assertSame($tolto->impronta_riga, $nuovo->impronta_precedente);
        $this->assertNull(CatenaDeiConsensi::verifica()['guasto']);
    }
}
