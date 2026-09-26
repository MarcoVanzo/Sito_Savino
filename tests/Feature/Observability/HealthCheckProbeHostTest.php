<?php

namespace Tests\Feature\Observability;

use Illuminate\Http\Middleware\TrustHosts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La sonda di App Platform interroga `/up` usando come Host l'indirizzo IP del
 * pod, non il dominio del sito. Con la sola lista basata su APP_URL, Symfony
 * rifiuta quelle richieste con 400 e l'istanza non passa MAI l'health check:
 * il deploy fallisce e DigitalOcean fa rollback automatico.
 *
 * È esattamente quello che è successo al primo rilascio di questo health check.
 * Finché il controllo era TCP il problema non poteva emergere, perché nessuno
 * faceva richieste HTTP interne.
 */
class HealthCheckProbeHostTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function trustedHostPatterns(): array
    {
        config(['app.trusted_hosts' => []]);
        config(['app.url' => 'https://sito-savino.ondigitalocean.app']);

        return app(TrustHosts::class)->hosts();
    }

    private function hostIsTrusted(string $host, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('{'.$pattern.'}i', $host)) {
                return true;
            }
        }

        return false;
    }

    #[Test]
    public function il_dominio_del_sito_resta_ammesso(): void
    {
        $patterns = $this->trustedHostPatterns();

        $this->assertTrue($this->hostIsTrusted('sito-savino.ondigitalocean.app', $patterns));
    }

    #[Test]
    public function la_sonda_che_interroga_per_ip_e_ammessa(): void
    {
        $patterns = $this->trustedHostPatterns();

        // Indirizzi tipici della rete interna di App Platform.
        $this->assertTrue($this->hostIsTrusted('10.244.1.37', $patterns));
        $this->assertTrue($this->hostIsTrusted('100.127.61.89', $patterns));
        $this->assertTrue($this->hostIsTrusted('localhost', $patterns));
    }

    #[Test]
    public function un_dominio_estraneo_resta_rifiutato(): void
    {
        // È il motivo per cui la difesa esiste: un Host forgiato non deve
        // finire negli URL assoluti generati, a partire dai link di reset
        // password.
        $patterns = $this->trustedHostPatterns();

        $this->assertFalse($this->hostIsTrusted('sito-di-un-attaccante.example', $patterns));
        $this->assertFalse($this->hostIsTrusted('evil.com', $patterns));
    }

    /**
     * Le voci passate a Symfony sono espressioni regolari: senza ancore
     * `sito-savino.ondigitalocean.app` accettava anche un dominio che lo
     * contiene, e l'attaccante ne registra uno così con poca spesa.
     */
    #[Test]
    public function un_dominio_che_contiene_quello_del_sito_resta_rifiutato(): void
    {
        $patterns = $this->trustedHostPatterns();

        $this->assertFalse($this->hostIsTrusted('sito-savino.ondigitalocean.app.evil.com', $patterns));
        $this->assertFalse($this->hostIsTrusted('evil-sito-savino.ondigitalocean.app', $patterns));
        $this->assertFalse($this->hostIsTrusted('sito-savinoXondigitalocean.app', $patterns));
        $this->assertTrue($this->hostIsTrusted('www.sito-savino.ondigitalocean.app', $patterns));
    }

    #[Test]
    public function funziona_anche_con_app_url_senza_schema(): void
    {
        config(['app.trusted_hosts' => []]);
        config(['app.url' => 'sito-savino.ondigitalocean.app']);
        $patterns = app(TrustHosts::class)->hosts();

        $this->assertTrue($this->hostIsTrusted('sito-savino.ondigitalocean.app', $patterns));
        $this->assertFalse($this->hostIsTrusted('sito-savino.ondigitalocean.app.evil.com', $patterns));
    }

    /**
     * Per la sonda bastano gli indirizzi della rete interna: un IP pubblico
     * come Host non ha nessun motivo di arrivare.
     */
    #[Test]
    public function come_host_sono_ammessi_solo_ip_privati(): void
    {
        $patterns = $this->trustedHostPatterns();

        $this->assertTrue($this->hostIsTrusted('192.168.1.5', $patterns));
        $this->assertTrue($this->hostIsTrusted('172.20.0.3', $patterns));
        $this->assertTrue($this->hostIsTrusted('127.0.0.1', $patterns));
        $this->assertFalse($this->hostIsTrusted('8.8.8.8', $patterns));
        $this->assertFalse($this->hostIsTrusted('172.32.0.1', $patterns));
        $this->assertFalse($this->hostIsTrusted('100.128.0.1', $patterns));
        $this->assertFalse($this->hostIsTrusted('10.0.0.1.evil.com', $patterns));
    }

    #[Test]
    public function anche_trusted_hosts_viene_ancorato(): void
    {
        config(['app.trusted_hosts' => ['savinodelbenevolley.it']]);
        config(['app.url' => 'https://sito-savino.ondigitalocean.app']);
        $patterns = app(TrustHosts::class)->hosts();

        $this->assertTrue($this->hostIsTrusted('savinodelbenevolley.it', $patterns));
        $this->assertTrue($this->hostIsTrusted('www.savinodelbenevolley.it', $patterns));
        $this->assertFalse($this->hostIsTrusted('savinodelbenevolley.it.evil.com', $patterns));
        $this->assertFalse($this->hostIsTrusted('savinodelbenevolleyXit', $patterns));
        // La sonda interna passa anche con la lista esplicita.
        $this->assertTrue($this->hostIsTrusted('10.244.1.37', $patterns));
    }
}
