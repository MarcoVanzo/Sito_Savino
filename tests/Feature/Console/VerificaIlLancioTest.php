<?php

namespace Tests\Feature\Console;

use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il controllo di prontezza al lancio deve fallire quando qualcosa manca.
 *
 * Un comando che dice sempre "tutto bene" e' peggio di non averlo: si lancia
 * il giorno del passaggio, si legge il verde e si sposta il DNS.
 */
class VerificaIlLancioTest extends TestCase
{
    use RefreshDatabase;

    /** Un ambiente senza blocchi, da cui i test tolgono una cosa per volta. */
    private function tuttoAPosto(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://savinodelbenevolley.it',
            'services.preview_auth.enabled' => false,
            'mail.default' => 'resend',
            'mail.from.address' => 'noreply@savinodelbenevolley.it',
            'services.paypal.client_id' => 'id',
            'services.paypal.client_secret' => 'segreto',
            'services.paypal.mode' => 'live',
            'services.paypal.webhook_id' => 'WH-123',
            // Come sta in produzione: Stripe non ha chiavi. `.env.testing` ne
            // ha di finte, che renderebbero il gateway "configurato" e
            // toglierebbero significato al caso qui sotto.
            'services.stripe.secret' => '',
            'sentry.dsn' => 'https://esempio@sentry.io/1',
        ]);

        Post::factory()->create(['published_at' => now()->subDay()]);
    }

    #[Test]
    public function passa_quando_non_manca_niente(): void
    {
        $this->tuttoAPosto();

        $this->artisan('verifica:lancio')->assertSuccessful();
    }

    /**
     * Il 23/09/2026 worker e scheduler giravano con `APP_KEY` vuota: nessun
     * errore da nessuna parte, ma la sincronizzazione notturna di Meta
     * falliva da sempre (`social_accounts.access_token` ha il cast
     * `encrypted`) e i link firmati nati in coda non sarebbero stati
     * verificabili dal web.
     */
    #[Test]
    public function blocca_se_manca_la_chiave_applicativa(): void
    {
        $this->tuttoAPosto();
        config(['app.key' => '']);

        $this->artisan('verifica:lancio')
            ->expectsOutputToContain('APP_KEY assente')
            ->assertFailed();
    }

    /**
     * La voce che pesa di piu': senza posta un cliente paga e non riceve
     * niente — ne' la conferma d'ordine, ne' la spedizione, ne' il rimborso.
     */
    #[Test]
    public function blocca_se_la_posta_finisce_nel_log(): void
    {
        $this->tuttoAPosto();
        config(['mail.default' => 'log']);

        $this->artisan('verifica:lancio')
            ->expectsOutputToContain('Nessuna email esce')
            ->assertFailed();
    }

    #[Test]
    public function blocca_un_mittente_non_vero(): void
    {
        $this->tuttoAPosto();
        config(['mail.from.address' => 'hello@example.com']);

        $this->artisan('verifica:lancio')->assertFailed();
    }

    #[Test]
    public function blocca_l_indirizzo_provvisorio_o_locale(): void
    {
        $this->tuttoAPosto();
        config(['app.url' => 'http://localhost']);

        $this->artisan('verifica:lancio')->assertFailed();
    }

    /**
     * L'indirizzo `ondigitalocean.app` non e' un errore finche' il dominio non
     * e' passato: e' una cosa da guardare, non un blocco.
     */
    #[Test]
    public function segnala_ma_non_blocca_l_indirizzo_di_digitalocean(): void
    {
        $this->tuttoAPosto();
        config(['app.url' => 'https://seashell-app-47mmf.ondigitalocean.app']);

        $this->artisan('verifica:lancio')
            ->expectsOutputToContain('Ancora l\'indirizzo provvisorio')
            ->assertSuccessful();
    }

    #[Test]
    public function blocca_il_sito_lasciato_dietro_la_password(): void
    {
        $this->tuttoAPosto();
        config(['services.preview_auth.enabled' => true]);

        $this->artisan('verifica:lancio')->assertFailed();
    }

    #[Test]
    public function blocca_il_debug_acceso_in_produzione(): void
    {
        $this->tuttoAPosto();
        config(['app.debug' => true]);

        $this->artisan('verifica:lancio')->assertFailed();
    }

    /**
     * Il bonifico e' sempre "configurato": da solo non fa un negozio.
     */
    #[Test]
    public function blocca_se_resta_solo_il_bonifico(): void
    {
        $this->tuttoAPosto();
        config([
            'services.paypal.client_id' => '',
            'services.paypal.client_secret' => '',
            'services.stripe.secret' => '',
        ]);

        $this->artisan('verifica:lancio')
            ->expectsOutputToContain('Nessun pagamento elettronico')
            ->assertFailed();
    }

    #[Test]
    public function blocca_paypal_senza_webhook(): void
    {
        $this->tuttoAPosto();
        config(['services.paypal.webhook_id' => '']);

        $this->artisan('verifica:lancio')->assertFailed();
    }

    /**
     * Il webhook configurato non si puo' validare da qui: va chiesto a PayPal.
     * Il comando deve dirlo, invece di far credere di averlo controllato.
     */
    #[Test]
    public function rimanda_a_paypal_verifica_per_il_webhook(): void
    {
        $this->tuttoAPosto();

        $this->artisan('verifica:lancio')
            ->expectsOutputToContain('paypal:verifica')
            ->assertSuccessful();
    }

    #[Test]
    public function segnala_il_negozio_chiuso_senza_bloccare(): void
    {
        $this->tuttoAPosto();
        SiteSetting::set('shop.enabled', false);

        $this->artisan('verifica:lancio')
            ->expectsOutputToContain('shop:stato')
            ->assertSuccessful();
    }

    #[Test]
    public function blocca_l_archivio_delle_notizie_vuoto(): void
    {
        $this->tuttoAPosto();
        Post::query()->delete();

        $this->artisan('verifica:lancio')->assertFailed();
    }

    #[Test]
    public function segnala_le_notizie_ferme_da_troppo(): void
    {
        $this->tuttoAPosto();
        Post::query()->delete();
        Post::factory()->create(['published_at' => now()->subDays(30)]);

        $this->artisan('verifica:lancio')
            ->expectsOutputToContain('news:importa-dal-vecchio-sito')
            ->assertSuccessful();
    }
}
