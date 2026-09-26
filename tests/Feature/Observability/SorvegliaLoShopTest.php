<?php

namespace Tests\Feature\Observability;

use App\Models\SiteSetting;
use App\Services\AvvisoTecnico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
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
            // Stripe con una chiave finta: senza, le aste avviserebbero in
            // ogni test (la verifica della carta per offrire passa da Stripe,
            // e PayPal qui è spento). Ci sono test apposta.
            'services.stripe.secret' => 'sk_test_finto',
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

    /**
     * Il rilascio vero: `start.sh` esegue `cache:clear` sullo store
     * predefinito. Lo stato della sorveglianza sta nello store `persistente`
     * e deve uscirne intatto.
     */
    private function rilascio(): void
    {
        Artisan::call('cache:clear');
    }

    #[Test]
    public function un_negozio_gia_chiuso_al_primo_giro_avvisa_una_volta_al_giorno(): void
    {
        // Senza stato precedente un negozio spento da un Salva caduto fra
        // l'ultimo giro e il deploy non deve passare in silenzio. Lo stesso
        // stato non si riannuncia: né dopo un rilascio, né se lo store
        // persistente si svuota (lì vale il silenziatore di un giorno).
        SiteSetting::set('shop.enabled', '0');

        $this->giro();
        $this->rilascio();
        $this->giro();
        AvvisoTecnico::memoria()->forget('sorveglianza:interruttore:negozio');
        $this->giro();

        $this->assertSame(['[Sito Savino] Il negozio è chiuso'], $this->oggetti());
    }

    #[Test]
    public function un_guasto_che_dura_non_si_riannuncia_a_ogni_rilascio(): void
    {
        $this->accoda('default', minutiFa: 20);

        $this->giro();
        $this->rilascio();
        $this->giro();

        $this->assertSame(['[Sito Savino] La coda dei job è ferma'], $this->oggetti());
    }

    #[Test]
    public function un_avviso_non_partito_si_ritenta_al_giro_dopo(): void
    {
        // Prima lo stato si scriveva prima dell'invio: con Resend giù in
        // quel momento il guasto restava "già annunciato" per sempre.
        $this->accoda('default', minutiFa: 20);

        Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('Resend irraggiungibile'));
        Mail::shouldReceive('raw')->once();

        $this->giro();
        $this->giro();
        $this->giro();

        $this->assertTrue(AvvisoTecnico::memoria()->get('sorveglianza:guasto:coda'));
    }

    #[Test]
    public function un_guasto_trovato_senza_destinatari_si_annuncia_quando_tornano(): void
    {
        // Con AVVISI_EMAIL vuota nessuno ha saputo niente: il guasto non
        // può restare "già annunciato".
        $this->accoda('default', minutiFa: 20);
        config(['services.avvisi.email' => '']);
        $this->giro();

        config(['services.avvisi.email' => 'marco@example.com']);
        $this->giro();

        $this->assertSame(['[Sito Savino] La coda dei job è ferma'], $this->oggetti());
    }

    #[Test]
    public function paypal_in_difficolta_non_e_ne_guasto_ne_guarigione(): void
    {
        $this->conPayPal();
        AvvisoTecnico::memoria()->forever('sorveglianza:guasto:paypal', true);
        Http::fake(['*' => Http::response('', 503)]);

        $this->giro();

        // Nessun "non configurato", nessun "risolto": il guasto ricordato
        // resta, e il controllo si ripete al giro dopo invece che fra un'ora.
        $this->assertSame([], $this->oggetti());
        $this->assertTrue(AvvisoTecnico::memoria()->get('sorveglianza:guasto:paypal'));
        $this->assertNull(AvvisoTecnico::memoria()->get('sorveglianza:paypal-controllato'));
    }

    #[Test]
    public function paypal_tolto_dal_checkout_dimentica_il_guasto(): void
    {
        // Se PayPal torna fra i metodi ancora rotto, va riannunciato.
        $this->conPayPal();
        AvvisoTecnico::memoria()->forever('sorveglianza:guasto:paypal', true);
        SiteSetting::set('shop.active_payment_gateways', 'bank_transfer');

        $this->giro();

        $this->assertNull(AvvisoTecnico::memoria()->get('sorveglianza:guasto:paypal'));
        // Nessun avviso su PayPal. Resta quello delle aste, accese di serie:
        // con il solo bonifico il vincitore non ha un metodo per pagare.
        $this->assertSame(['[Sito Savino] Le aste non si possono pagare'], $this->oggetti());
    }

    private function conPayPal(): void
    {
        config([
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'id',
            'services.paypal.client_secret' => 'secret',
            'services.paypal.webhook_id' => 'WH-1',
        ]);
        SiteSetting::set('shop.active_payment_gateways', 'paypal,bank_transfer');
    }

    #[Test]
    public function aste_accese_senza_stripe_ne_paypal_avvisa(): void
    {
        config(['services.stripe.secret' => null]);
        SiteSetting::set('shop.active_payment_gateways', 'bank_transfer');

        $this->giro();
        $this->giro();

        // Il bonifico non basta al vincitore, e senza Stripe non si verifica
        // la carta con cui si offre.
        $this->assertSame([
            '[Sito Savino] Le aste non si possono pagare',
            '[Sito Savino] Le aste non accettano nuovi offerenti',
        ], $this->oggetti());
    }

    #[Test]
    public function con_paypal_il_vincitore_puo_pagare_anche_senza_stripe(): void
    {
        config([
            'services.stripe.secret' => null,
            'services.paypal.client_id' => 'id-finto',
            'services.paypal.client_secret' => 'segreto-finto',
        ]);
        // `paypal:verifica` interrogherebbe l'API vera: il controllo orario
        // risulta già fatto.
        AvvisoTecnico::memoria()->put('sorveglianza:paypal-controllato', true, 3600);

        $this->giro();

        // Il checkout del vincitore ha PayPal; resta vero che senza Stripe
        // non si verifica la carta per offrire.
        $this->assertSame(['[Sito Savino] Le aste non accettano nuovi offerenti'], $this->oggetti());
    }

    #[Test]
    public function paypal_spento_dal_pannello_non_conta_per_le_aste(): void
    {
        config([
            'services.paypal.client_id' => 'id-finto',
            'services.paypal.client_secret' => 'segreto-finto',
        ]);
        AvvisoTecnico::memoria()->put('sorveglianza:paypal-controllato', true, 3600);
        // Stripe ha le chiavi ma non è fra i metodi attivi, PayPal nemmeno:
        // al vincitore resterebbe il solo bonifico, che le aste non offrono.
        SiteSetting::set('shop.active_payment_gateways', 'bank_transfer');

        $this->giro();

        $this->assertSame(['[Sito Savino] Le aste non si possono pagare'], $this->oggetti());
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

        // Il bonifico rimette in piedi lo shop, non le aste: il vincitore
        // paga solo con Stripe o PayPal.
        $this->assertSame([
            '[Sito Savino] Il checkout non offre nessun metodo di pagamento',
            '[Sito Savino] Le aste non si possono pagare',
            '[Sito Savino] Risolto: il checkout non offre nessun metodo di pagamento',
        ], $this->oggetti());
    }

    #[Test]
    public function un_gateway_attivo_ma_senza_credenziali_non_conta(): void
    {
        config(['services.stripe.secret' => null]);
        SiteSetting::set('auctions.enabled', '0');
        SiteSetting::set('shop.active_payment_gateways', 'stripe');
        AvvisoTecnico::memoria()->forever('sorveglianza:interruttore:aste', false);

        $this->giro();

        $this->assertSame(['[Sito Savino] Il checkout non offre nessun metodo di pagamento'], $this->oggetti());
    }

    #[Test]
    public function a_negozio_chiuso_i_metodi_di_pagamento_non_contano(): void
    {
        SiteSetting::set('shop.enabled', '0');
        SiteSetting::set('shop.active_payment_gateways', '');
        // Chiuso già al giro precedente: nessun cambio da annunciare.
        AvvisoTecnico::memoria()->forever('sorveglianza:interruttore:negozio', false);

        $this->giro();

        // Il checkout del vincitore non dipende da `shop.enabled` (solo da
        // `auctions.enabled`): a negozio chiuso le aste restano da pagare.
        $this->assertSame(['[Sito Savino] Le aste non si possono pagare'], $this->oggetti());
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
