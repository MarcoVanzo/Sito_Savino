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
}
