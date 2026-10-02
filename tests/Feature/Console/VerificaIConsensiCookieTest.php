<?php

namespace Tests\Feature\Console;

use App\Models\ConsensoCookie;
use App\Services\CatenaDeiConsensi;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `consensi:verifica` ricalcola la catena delle impronte del registro dei
 * consensi ai cookie (Garante, 25/09/2025: data e origine del consenso non
 * devono potersi modificare).
 *
 * Il valore del comando sta tutto nei casi in cui deve dire di no: una data
 * ritoccata, una riga tolta in mezzo, righe sparite in testa senza passare da
 * `consensi:pota`. Se uno di questi passasse, il comando direbbe "integro" su
 * un registro manomesso.
 */
class VerificaIConsensiCookieTest extends TestCase
{
    use RefreshDatabase;

    private function consenso(bool $marketing = false): ConsensoCookie
    {
        return CatenaDeiConsensi::registra([
            'riferimento' => ConsensoCookie::nuovoRiferimento(),
            'statistiche' => true,
            'marketing' => $marketing,
            'azione' => 'concesso',
            'versione' => ConsensoCookie::VERSIONE,
            'impronta_ip' => ConsensoCookie::improntaDi('203.0.113.7'),
        ]);
    }

    public function test_un_registro_intatto_passa_e_dice_l_ultima_impronta(): void
    {
        $this->consenso();
        $ultimo = $this->consenso(true);

        $this->artisan('consensi:verifica')
            ->expectsOutputToContain('Catena integra: 2 righe. Ultima impronta: '.$ultimo->impronta_riga)
            ->assertSuccessful();
    }

    public function test_un_registro_vuoto_non_e_un_guasto(): void
    {
        $this->artisan('consensi:verifica')
            ->expectsOutputToContain('vuoto')
            ->assertSuccessful();
    }

    public function test_una_data_ritoccata_si_trova(): void
    {
        $this->consenso();
        $ritoccato = $this->consenso();
        $this->consenso();

        DB::table('consensi_cookie')->where('id', $ritoccato->id)
            ->update(['created_at' => now()->subYear()]);

        $this->artisan('consensi:verifica')
            ->expectsOutputToContain('riga '.$ritoccato->id.': il contenuto della riga è stato cambiato')
            ->assertFailed();
    }

    public function test_una_scelta_ritoccata_si_trova(): void
    {
        $ritoccato = $this->consenso(false);

        DB::table('consensi_cookie')->where('id', $ritoccato->id)->update(['marketing' => true]);

        $this->artisan('consensi:verifica')->assertFailed();
    }

    public function test_una_riga_tolta_in_mezzo_si_trova(): void
    {
        $this->consenso();
        $tolto = $this->consenso();
        $dopo = $this->consenso();

        DB::table('consensi_cookie')->where('id', $tolto->id)->delete();

        $this->artisan('consensi:verifica')
            ->expectsOutputToContain('riga '.$dopo->id.': la catena si interrompe')
            ->assertFailed();
    }

    public function test_righe_tolte_in_testa_senza_la_potatura_si_trovano(): void
    {
        // La potatura lascia l'ancora; una cancellazione a mano no. Senza
        // l'ancora le due cose sarebbero indistinguibili.
        $tolto = $this->consenso();
        $primo = $this->consenso();

        DB::table('consensi_cookie')->where('id', $tolto->id)->delete();

        $this->artisan('consensi:verifica')
            ->expectsOutputToContain('riga '.$primo->id.': la prima riga non si aggancia all\'ultima potatura')
            ->assertFailed();
    }

    public function test_una_riga_scritta_fuori_dalla_catena_si_trova(): void
    {
        $this->consenso();

        DB::table('consensi_cookie')->insert([
            'riferimento' => ConsensoCookie::nuovoRiferimento(),
            'statistiche' => true,
            'marketing' => true,
            'azione' => 'concesso',
            'versione' => ConsensoCookie::VERSIONE,
            'created_at' => now(),
        ]);

        $this->artisan('consensi:verifica')
            ->expectsOutputToContain('la riga non è sigillata')
            ->assertFailed();
    }

    public function test_le_righe_di_prima_della_catena_si_sigillano_in_ordine_di_id(): void
    {
        // Come la migrazione trova il registro: righe senza impronta.
        foreach ([false, true, false] as $marketing) {
            DB::table('consensi_cookie')->insert([
                'riferimento' => ConsensoCookie::nuovoRiferimento(),
                'statistiche' => true,
                'marketing' => $marketing,
                'azione' => 'concesso',
                'versione' => '2026-09-26',
                'created_at' => now()->subDays(3),
            ]);
        }

        $this->assertSame(3, CatenaDeiConsensi::sigillaIMancanti());
        // Idempotente: la migrazione gira a ogni avvio.
        $this->assertSame(0, CatenaDeiConsensi::sigillaIMancanti());

        $righe = DB::table('consensi_cookie')->orderBy('id')->get();
        $this->assertNull($righe[0]->impronta_precedente);
        $this->assertSame($righe[0]->impronta_riga, $righe[1]->impronta_precedente);
        $this->assertSame($righe[1]->impronta_riga, $righe[2]->impronta_precedente);

        // Il primo consenso nuovo si attacca in coda.
        $nuovo = $this->consenso();
        $this->assertSame($righe[2]->impronta_riga, $nuovo->impronta_precedente);

        $this->artisan('consensi:verifica')->assertSuccessful();
    }

    public function test_una_riga_lasciata_dal_codice_vecchio_durante_un_rilascio_si_sigilla_al_consenso_dopo(): void
    {
        $this->consenso();

        DB::table('consensi_cookie')->insert([
            'riferimento' => ConsensoCookie::nuovoRiferimento(),
            'statistiche' => false,
            'marketing' => false,
            'azione' => 'rifiutato',
            'versione' => ConsensoCookie::VERSIONE,
            'created_at' => now(),
        ]);

        $this->consenso();

        $this->artisan('consensi:verifica')
            ->expectsOutputToContain('Catena integra: 3 righe')
            ->assertSuccessful();
    }

    public function test_e_pianificata_e_quindi_avvisa_se_fallisce(): void
    {
        // Registrata sopra il ciclo di AvvisoDelPianificatore (§24): un evento
        // aggiunto sotto resterebbe muto.
        $eventi = collect(app(Schedule::class)->events())
            ->filter(fn ($evento) => str_contains((string) $evento->command, 'consensi:verifica'));

        $this->assertCount(1, $eventi);
    }

    public function test_chi_ritocca_una_riga_e_ricalcola_l_impronta_senza_il_segreto_viene_scoperto(): void
    {
        config(['services.consensi.sale' => 'segreto-vero']);

        $ritoccato = $this->consenso(false);

        // Chi scrive nel database rifà l'impronta della riga, ma senza il
        // segreto: con uno sha256 semplice ci riuscirebbe, con l'HMAC no.
        $riga = (array) DB::table('consensi_cookie')->where('id', $ritoccato->id)->first();
        $riga['marketing'] = 1;
        $finta = hash('sha256', json_encode($riga));
        config(['services.consensi.sale' => 'segreto-indovinato']);
        $conUnAltroSegreto = CatenaDeiConsensi::impronta($riga, $riga['impronta_precedente']);
        config(['services.consensi.sale' => 'segreto-vero']);

        foreach ([$finta, $conUnAltroSegreto] as $impronta) {
            DB::table('consensi_cookie')->where('id', $ritoccato->id)
                ->update(['marketing' => true, 'impronta_riga' => $impronta]);

            $this->artisan('consensi:verifica')
                ->expectsOutputToContain('il contenuto della riga è stato cambiato')
                ->assertFailed();
        }
    }

    public function test_l_impronta_e_un_hmac_con_il_sale_dedicato_e_ripiega_sulla_chiave_dell_applicazione(): void
    {
        $riga = ['riferimento' => 'r', 'statistiche' => true, 'marketing' => false, 'created_at' => '2026-10-02 10:00:00'];

        config(['services.consensi.sale' => 'segreto', 'app.key' => 'base64:chiave']);
        $conIlSale = CatenaDeiConsensi::impronta($riga, null);

        config(['services.consensi.sale' => null]);
        $conLaChiave = CatenaDeiConsensi::impronta($riga, null);

        $this->assertNotSame($conIlSale, $conLaChiave);

        // Senza segreto dedicato vale APP_KEY: stessa impronta che si avrebbe
        // impostando CONSENSI_SALE uguale alla chiave.
        config(['services.consensi.sale' => 'base64:chiave']);
        $this->assertSame($conLaChiave, CatenaDeiConsensi::impronta($riga, null));
    }

    public function test_cambiare_il_segreto_fa_risultare_alterato_il_registro(): void
    {
        // È il prezzo dell'HMAC, e va saputo: CONSENSI_SALE non si ruota.
        config(['services.consensi.sale' => 'primo']);
        $this->consenso();

        config(['services.consensi.sale' => 'secondo']);

        $this->artisan('consensi:verifica')->assertFailed();
    }
}
