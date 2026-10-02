<?php

namespace Tests\Feature\Newsletter;

use App\Jobs\UnsubscribeNewsletterFromActiveCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * `newsletter:allinea-disiscritti` porta sul sito le disiscrizioni fatte su
 * ActiveCampaign (Preference Center, link delle campagne), che senza webhook
 * lasciavano la riga "iscritta" con nome e IP.
 */
class AllineaIDisiscrittiDaActiveCampaignTest extends TestCase
{
    use RefreshDatabase;

    private const CONTATTI = 'https://savino.api-us1.com/api/3/contacts*';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config([
            'services.activecampaign.url' => 'https://savino.api-us1.com',
            'services.activecampaign.key' => 'chiave-di-prova',
            'services.activecampaign.list_id' => 7,
        ]);
    }

    /**
     * @param  list<string>  $email
     * @return array<string, mixed>
     */
    private function pagina(array $email, int $totale): array
    {
        return [
            'contacts' => array_map(fn ($indirizzo) => ['id' => '1', 'email' => $indirizzo], $email),
            'meta' => ['total' => (string) $totale],
        ];
    }

    public function test_segna_i_disiscritti_su_activecampaign_e_cancella_nome_e_ip(): void
    {
        $uscita = NewsletterSubscriber::factory()->create(['email' => 'uscita@example.com', 'ip_address' => '203.0.113.7']);
        $rimasta = NewsletterSubscriber::factory()->create(['email' => 'rimasta@example.com']);

        Http::fake([self::CONTATTI => Http::response($this->pagina(['Uscita@Example.com'], 1))]);

        $this->artisan('newsletter:allinea-disiscritti')
            ->expectsOutputToContain('1 disiscritti su ActiveCampaign, 1 segnati ora anche sul sito')
            ->assertSuccessful();

        $dopo = $uscita->fresh();
        $this->assertNotNull($dopo->unsubscribed_at);
        $this->assertNull($dopo->first_name);
        $this->assertNull($dopo->ip_address);
        $this->assertNull($rimasta->fresh()->unsubscribed_at);

        // Su ActiveCampaign l'uscita c'è già: non si rimanda.
        Queue::assertNotPushed(UnsubscribeNewsletterFromActiveCampaign::class);

        Http::assertSent(fn (Request $richiesta) => $richiesta['listid'] == 7
            && $richiesta['status'] == 2
            && $richiesta->hasHeader('Api-Token', 'chiave-di-prova'));
    }

    public function test_legge_tutte_le_pagine(): void
    {
        $email = array_map(fn ($i) => "persona{$i}@example.com", range(1, 150));
        $ultima = NewsletterSubscriber::factory()->create(['email' => 'persona150@example.com']);

        Http::fake([self::CONTATTI => Http::sequence()
            ->push($this->pagina(array_slice($email, 0, 100), 150))
            ->push($this->pagina(array_slice($email, 100), 150)),
        ]);

        $this->artisan('newsletter:allinea-disiscritti')->assertSuccessful();

        $this->assertNotNull($ultima->fresh()->unsubscribed_at);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $richiesta) => $richiesta['offset'] == 100);
    }

    public function test_non_tocca_chi_ha_appena_riconfermato_e_aspetta_di_tornare_in_lista(): void
    {
        // Su ActiveCampaign è ancora disiscritto dalla volta prima; sul sito
        // ha confermato di nuovo e il job che lo rimette in lista non è
        // ancora passato.
        $rientrato = NewsletterSubscriber::factory()->notSynced()->create(['email' => 'rientrato@example.com']);

        Http::fake([self::CONTATTI => Http::response($this->pagina(['rientrato@example.com'], 1))]);

        $this->artisan('newsletter:allinea-disiscritti')->assertSuccessful();

        $this->assertNull($rientrato->fresh()->unsubscribed_at);
    }

    public function test_con_prova_non_cambia_niente(): void
    {
        $uscita = NewsletterSubscriber::factory()->create(['email' => 'uscita@example.com']);

        Http::fake([self::CONTATTI => Http::response($this->pagina(['uscita@example.com'], 1))]);

        $this->artisan('newsletter:allinea-disiscritti', ['--prova' => true])
            ->expectsOutputToContain('1 iscritti da segnare')
            ->assertSuccessful();

        $this->assertNull($uscita->fresh()->unsubscribed_at);
    }

    public function test_un_errore_transitorio_non_fa_fallire_il_comando(): void
    {
        $uscita = NewsletterSubscriber::factory()->create(['email' => 'uscita@example.com']);

        Http::fake([self::CONTATTI => Http::response('Service Unavailable', 503)]);

        $this->artisan('newsletter:allinea-disiscritti')
            ->expectsOutputToContain('si riprova al prossimo giro')
            ->assertSuccessful();

        $this->assertNull($uscita->fresh()->unsubscribed_at);
    }

    public function test_una_connessione_mancata_e_transitoria(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->artisan('newsletter:allinea-disiscritti')->assertSuccessful();
    }

    public function test_una_chiave_rifiutata_fa_fallire_il_comando_e_quindi_avvisa(): void
    {
        Http::fake([self::CONTATTI => Http::response(['message' => 'No Result found'], 403)]);

        $this->artisan('newsletter:allinea-disiscritti')
            ->expectsOutputToContain('403')
            ->assertFailed();
    }

    public function test_una_risposta_illeggibile_fa_fallire_il_comando(): void
    {
        Http::fake([self::CONTATTI => Http::response(['qualcosa' => 'altro'])]);

        $this->artisan('newsletter:allinea-disiscritti')->assertFailed();
    }

    public function test_senza_activecampaign_configurato_non_chiama_niente(): void
    {
        config(['services.activecampaign.key' => '']);
        Http::fake();

        $this->artisan('newsletter:allinea-disiscritti')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_e_pianificato_sopra_il_ciclo_degli_avvisi(): void
    {
        $eventi = collect(app(Schedule::class)->events())
            ->filter(fn ($evento) => str_contains((string) $evento->command, 'newsletter:allinea-disiscritti'));

        $this->assertCount(1, $eventi);
    }
}
