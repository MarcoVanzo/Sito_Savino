<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\ConsensoCookie;
use App\Models\Page;
use App\Models\VersioneTestiConsenso;
use App\Services\CatenaDeiConsensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function registra_la_scelta_e_restituisce_il_riferimento(): void
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

    #[Test]
    public function non_conserva_l_indirizzo_in_chiaro(): void
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

    #[Test]
    public function chi_cambia_idea_aggiunge_una_riga_alla_propria_storia(): void
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

    #[Test]
    public function un_no_alla_prima_richiesta_e_un_rifiuto_non_una_revoca(): void
    {
        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => false, 'marketing' => false])
            ->assertSuccessful();

        $this->assertSame('rifiutato', ConsensoCookie::firstOrFail()->azione);
    }

    #[Test]
    public function una_scelta_parziale_di_chi_torna_e_un_aggiornamento(): void
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

    #[Test]
    public function rifiuta_una_richiesta_senza_le_due_scelte(): void
    {
        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => true])
            ->assertStatus(422);

        $this->assertSame(0, ConsensoCookie::count());
    }

    #[Test]
    public function rifiuta_un_riferimento_inventato(): void
    {
        $this->postJson(route('consenso-cookie.registra'), [
            'statistiche' => true,
            'marketing' => true,
            'riferimento' => 'non-e-un-uuid',
        ])->assertStatus(422);

        $this->assertSame(0, ConsensoCookie::count());
    }

    #[Test]
    public function tiene_un_user_agent_lunghissimo_senza_perdere_il_consenso(): void
    {
        $this->withHeaders(['User-Agent' => str_repeat('x', 900)])
            ->postJson(route('consenso-cookie.registra'), ['statistiche' => true, 'marketing' => true])
            ->assertSuccessful();

        $this->assertLessThanOrEqual(255, strlen(ConsensoCookie::firstOrFail()->user_agent));
    }

    /**
     * La Cookie Policy la crea già una migrazione: si riscrive il testo.
     *
     * @param  array<string, string>  $testo
     */
    private function cookiePolicy(array $testo): void
    {
        $pagina = Page::firstOrNew(['slug' => 'cookie-policy']);
        $pagina->setTranslations('title', ['it' => 'Cookie Policy', 'en' => 'Cookie Policy']);
        $pagina->setTranslations('content', $testo);
        $pagina->status = PostStatus::Published;
        $pagina->save();
    }

    #[Test]
    public function l_impronta_dell_ip_usa_il_sale_dedicato_e_non_la_chiave_dell_applicazione(): void
    {
        config(['services.consensi.sale' => 'sale-dedicato', 'app.key' => 'base64:chiave-vecchia']);

        $this->assertSame(hash('sha256', '203.0.113.7|sale-dedicato'), ConsensoCookie::improntaDi('203.0.113.7'));

        // Ruotare APP_KEY non cambia le impronte: era il motivo del sale suo.
        $prima = ConsensoCookie::improntaDi('203.0.113.7');
        config(['app.key' => 'base64:chiave-nuova']);
        $this->assertSame($prima, ConsensoCookie::improntaDi('203.0.113.7'));
    }

    #[Test]
    public function senza_sale_dedicato_ripiega_sulla_chiave_dell_applicazione(): void
    {
        // Il deploy non si rompe se CONSENSI_SALE non è ancora impostato: le
        // impronte restano quelle calcolate finora.
        config(['services.consensi.sale' => null, 'app.key' => 'base64:chiave']);

        $this->assertSame('base64:chiave', ConsensoCookie::sale());
        $this->assertSame(hash('sha256', '203.0.113.7|base64:chiave'), ConsensoCookie::improntaDi('203.0.113.7'));

        config(['services.consensi.sale' => '']);
        $this->assertSame('base64:chiave', ConsensoCookie::sale());
    }

    #[Test]
    public function il_consenso_porta_l_impronta_dei_testi_mostrati_e_l_archivio_li_conserva(): void
    {
        $this->cookiePolicy(['it' => '<p>Usiamo cookie tecnici.</p>', 'en' => '<p>We use technical cookies.</p>']);

        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => true, 'marketing' => false])
            ->assertSuccessful();

        $consenso = ConsensoCookie::firstOrFail();
        $versione = VersioneTestiConsenso::where('impronta', $consenso->impronta_testi)->firstOrFail();

        $this->assertSame(VersioneTestiConsenso::TIPO_COOKIE, $versione->tipo);
        // Chiunque può ricalcolare l'impronta dal contenuto conservato.
        $this->assertSame(hash('sha256', $versione->contenuto), $versione->impronta);

        $testi = $versione->testi();
        $it = json_decode((string) file_get_contents(resource_path('js/i18n/it.json')), true);
        $en = json_decode((string) file_get_contents(resource_path('js/i18n/en.json')), true);

        $this->assertSame(ConsensoCookie::VERSIONE, $testi['versione']);
        $this->assertSame($it['cookie']['description'], $testi['lingue']['it']['banner']['description']);
        $this->assertSame($en['cookie']['marketing_desc'], $testi['lingue']['en']['banner']['marketing_desc']);
        $this->assertSame('<p>Usiamo cookie tecnici.</p>', $testi['lingue']['it']['cookie_policy']['testo']);
        $this->assertSame('<p>We use technical cookies.</p>', $testi['lingue']['en']['cookie_policy']['testo']);
        $this->assertArrayHasKey('categorie', $testi['lingue']['it']['dichiarazione']);
    }

    #[Test]
    public function con_gli_stessi_testi_l_archivio_non_si_ripete_e_con_testi_nuovi_si_allunga(): void
    {
        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => true, 'marketing' => true]);
        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => false, 'marketing' => false]);

        $this->assertSame(1, VersioneTestiConsenso::count());

        // La redazione cambia la Cookie Policy: il consenso dopo vede un
        // testo diverso, e l'archivio lo conserva accanto al primo.
        $this->cookiePolicy(['it' => '<p>Testo nuovo.</p>']);

        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => true, 'marketing' => false]);

        $this->assertSame(2, VersioneTestiConsenso::count());
        $impronte = ConsensoCookie::orderBy('id')->pluck('impronta_testi');
        $this->assertSame($impronte[0], $impronte[1]);
        $this->assertNotSame($impronte[1], $impronte[2]);
    }

    #[Test]
    public function un_testo_archiviato_non_si_modifica_ne_si_cancella(): void
    {
        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => true, 'marketing' => true]);
        $versione = VersioneTestiConsenso::firstOrFail();

        try {
            $versione->update(['contenuto' => '{}']);
            $this->fail('Il testo archiviato è stato modificato.');
        } catch (\LogicException) {
        }

        try {
            $versione->delete();
            $this->fail('Il testo archiviato è stato cancellato.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, VersioneTestiConsenso::count());
    }

    #[Test]
    public function ogni_consenso_si_aggancia_al_precedente_nella_catena(): void
    {
        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => true, 'marketing' => true]);
        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => false, 'marketing' => false]);

        [$primo, $secondo] = ConsensoCookie::orderBy('id')->get()->all();

        $this->assertNull($primo->impronta_precedente);
        $this->assertSame($primo->impronta_riga, $secondo->impronta_precedente);
        $this->assertNull(CatenaDeiConsensi::verifica()['guasto']);
    }

    #[Test]
    public function un_consenso_registrato_non_si_modifica_ne_si_cancella_dal_modello(): void
    {
        $this->postJson(route('consenso-cookie.registra'), ['statistiche' => true, 'marketing' => true]);
        $consenso = ConsensoCookie::firstOrFail();

        $this->expectException(\LogicException::class);

        $consenso->update(['marketing' => false]);
    }
}
