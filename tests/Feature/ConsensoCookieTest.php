<?php

namespace Tests\Feature;

use App\Models\ConsensoCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il registro delle scelte fatte sul banner dei cookie.
 *
 * Serve a dimostrare il consenso (GDPR art. 7 §1): finora la scelta viveva solo
 * nel browser del visitatore e, cancellata la cronologia, della prova non
 * restava niente.
 */
class ConsensoCookieTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_la_scelta_e_restituisce_il_riferimento(): void
    {
        $risposta = $this->postJson(route('consenso-cookie.registra'), [
            'statistiche' => true,
            'marketing' => false,
        ]);

        $risposta->assertSuccessful()
            ->assertJsonStructure(['riferimento', 'registrato_il', 'versione']);

        $consenso = ConsensoCookie::firstOrFail();

        $this->assertTrue($consenso->statistiche);
        $this->assertFalse($consenso->marketing);
        $this->assertSame('concesso', $consenso->azione);
        $this->assertSame(ConsensoCookie::VERSIONE, $consenso->versione);
        $this->assertSame($risposta->json('riferimento'), $consenso->riferimento);
    }

    public function test_non_conserva_l_indirizzo_in_chiaro(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson(route('consenso-cookie.registra'), ['statistiche' => false, 'marketing' => false])
            ->assertSuccessful();

        $consenso = ConsensoCookie::firstOrFail();

        $this->assertNotNull($consenso->impronta_ip);
        $this->assertStringNotContainsString('203.0.113.7', $consenso->impronta_ip);
        $this->assertSame(64, strlen($consenso->impronta_ip));

        // L'impronta è stabile: due consensi dallo stesso indirizzo si
        // riconoscono, ma dall'impronta non si torna all'indirizzo.
        $this->assertSame(ConsensoCookie::improntaDi('203.0.113.7'), $consenso->impronta_ip);
        $this->assertNotSame(ConsensoCookie::improntaDi('203.0.113.8'), $consenso->impronta_ip);
    }

    public function test_chi_cambia_idea_aggiunge_una_riga_alla_propria_storia(): void
    {
        $riferimento = $this->postJson(route('consenso-cookie.registra'), [
            'statistiche' => true,
            'marketing' => true,
        ])->json('riferimento');

        $this->postJson(route('consenso-cookie.registra'), [
            'statistiche' => false,
            'marketing' => false,
            'riferimento' => $riferimento,
        ])->assertSuccessful();

        $consensi = ConsensoCookie::where('riferimento', $riferimento)->orderBy('id')->get();

        $this->assertCount(2, $consensi);
        $this->assertSame('concesso', $consensi[0]->azione);
        $this->assertSame('revocato', $consensi[1]->azione);
    }

    public function test_un_no_alla_prima_richiesta_e_un_rifiuto_non_una_revoca(): void
    {
        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => false, 'marketing' => false])
            ->assertSuccessful();

        $this->assertSame('rifiutato', ConsensoCookie::firstOrFail()->azione);
    }

    public function test_una_scelta_parziale_di_chi_torna_e_un_aggiornamento(): void
    {
        $riferimento = $this->postJson(route('consenso-cookie.registra'), [
            'statistiche' => false,
            'marketing' => false,
        ])->json('riferimento');

        $this->postJson(route('consenso-cookie.registra'), [
            'statistiche' => true,
            'marketing' => false,
            'riferimento' => $riferimento,
        ])->assertSuccessful();

        $this->assertSame('aggiornato', ConsensoCookie::orderByDesc('id')->firstOrFail()->azione);
    }

    public function test_rifiuta_una_richiesta_senza_le_due_scelte(): void
    {
        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => true])
            ->assertStatus(422);

        $this->assertSame(0, ConsensoCookie::count());
    }

    public function test_rifiuta_un_riferimento_inventato(): void
    {
        $this->postJson(route('consenso-cookie.registra'), [
            'statistiche' => true,
            'marketing' => true,
            'riferimento' => 'non-e-un-uuid',
        ])->assertStatus(422);

        $this->assertSame(0, ConsensoCookie::count());
    }

    public function test_tiene_un_user_agent_lunghissimo_senza_perdere_il_consenso(): void
    {
        $this->withHeaders(['User-Agent' => str_repeat('x', 900)])
            ->postJson(route('consenso-cookie.registra'), ['statistiche' => true, 'marketing' => true])
            ->assertSuccessful();

        $this->assertLessThanOrEqual(255, strlen(ConsensoCookie::firstOrFail()->user_agent));
    }
}
