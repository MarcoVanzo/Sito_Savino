<?php

namespace Tests\Feature\Observability;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `shop:sorveglia` avvisa quando una condizione cambia, non a ogni giro: un
 * negozio chiuso apposta per una settimana deve produrre due email, non mille.
 */
class SorvegliaLoShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.avvisi.email' => 'marco@example.com',
            // PayPal fuori dal giro: interrogherebbe l'API vera.
            'services.paypal.client_id' => null,
            'services.paypal.client_secret' => null,
            'services.stripe.secret' => null,
        ]);
    }

    /** @return list<string> */
    private function oggetti(): array
    {
        return AvvisoTecnicoTest::inviate()
            ->map(fn ($m) => $m->getOriginalMessage()->getSubject())
            ->values()
            ->all();
    }

    private function giro(): void
    {
        $this->artisan('shop:sorveglia')->assertSuccessful();
    }

    #[Test]
    public function tutto_in_ordine_nessuna_email(): void
    {
        $this->giro();
        $this->giro();

        $this->assertSame([], $this->oggetti());
    }

    #[Test]
    public function il_negozio_spento_avvisa_una_volta_e_poi_quando_riapre(): void
    {
        // Il primo giro registra lo stato: dopo un rilascio la cache è vuota
        // e non si sa se l'interruttore sia appena cambiato.
        $this->giro();

        SiteSetting::set('shop.enabled', '0');
        $this->giro();
        $this->giro();

        SiteSetting::set('shop.enabled', '1');
        $this->giro();

        $this->assertSame([
            '[Sito Savino] Il negozio è chiuso',
            '[Sito Savino] Il negozio è di nuovo aperto',
        ], $this->oggetti());
    }

    #[Test]
    public function un_negozio_gia_chiuso_al_primo_giro_non_avvisa(): void
    {
        SiteSetting::set('shop.enabled', '0');

        $this->giro();

        $this->assertSame([], $this->oggetti());
    }

    #[Test]
    public function checkout_senza_metodi_di_pagamento(): void
    {
        // È il 21/09/2026: `active_payment_gateways` scritto vuoto dal primo
        // Salva del pannello, negozio aperto, nessuno che possa pagare.
        SiteSetting::set('shop.active_payment_gateways', '');

        $this->giro();
        $this->giro();

        SiteSetting::set('shop.active_payment_gateways', 'bank_transfer');
        $this->giro();

        $this->assertSame([
            '[Sito Savino] Il checkout non offre nessun metodo di pagamento',
            '[Sito Savino] Risolto: il checkout non offre nessun metodo di pagamento',
        ], $this->oggetti());
    }

    #[Test]
    public function un_gateway_attivo_ma_senza_credenziali_non_conta(): void
    {
        SiteSetting::set('shop.active_payment_gateways', 'stripe');

        $this->giro();

        $this->assertSame(['[Sito Savino] Il checkout non offre nessun metodo di pagamento'], $this->oggetti());
    }

    #[Test]
    public function a_negozio_chiuso_i_metodi_di_pagamento_non_contano(): void
    {
        SiteSetting::set('shop.enabled', '0');
        SiteSetting::set('shop.active_payment_gateways', '');

        $this->giro();

        $this->assertSame([], $this->oggetti());
    }

    #[Test]
    public function la_coda_ferma_avvisa(): void
    {
        $this->accoda('default', minutiFa: 20);

        $this->giro();

        $this->assertSame(['[Sito Savino] La coda dei job è ferma'], $this->oggetti());
    }

    #[Test]
    public function un_job_appena_accodato_o_sulla_coda_ai_non_e_un_guasto(): void
    {
        // La coda `ai` si smaltisce dopo `default` e può restare indietro di
        // ore durante un recupero della gallery: non è un worker fermo.
        $this->accoda('default', minutiFa: 2);
        $this->accoda('ai', minutiFa: 120);

        $this->giro();

        $this->assertSame([], $this->oggetti());
    }

    private function accoda(string $coda, int $minutiFa): void
    {
        DB::table('jobs')->insert([
            'queue' => $coda,
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes($minutiFa)->getTimestamp(),
            'created_at' => now()->subMinutes($minutiFa)->getTimestamp(),
        ]);
    }
}
