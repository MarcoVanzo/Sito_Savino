<?php

namespace Tests\Unit;

use App\Services\Affiliazioni\ParserDelleAffiliate;
use PHPUnit\Framework\TestCase;

/**
 * Il parser lavora sulla pagina vera del sito precedente (fixture), non su un
 * markup inventato: quando quella pagina cambia, il test cade prima
 * dell'import.
 */
class ParserDelleAffiliateTest extends TestCase
{
    /** @var list<array{name: string, tier: string, url: ?string, logo: string}> */
    private array $societa;

    protected function setUp(): void
    {
        parent::setUp();

        $html = file_get_contents(__DIR__.'/../Fixtures/Affiliazioni/pagina-legacy.html');
        $this->societa = (new ParserDelleAffiliate)->analizza($html, 'https://savinodelbenevolley.it/affiliazioni/');
    }

    public function test_attribuisce_ogni_societa_al_titolo_che_la_precede(): void
    {
        $perLivello = [];

        foreach ($this->societa as $voce) {
            $perLivello[$voce['tier']][] = $voce['name'];
        }

        $this->assertSame(['Fusion Team Volley', 'Vola Valley'], $perLivello['main']);
        $this->assertContains('Nottolini Volley', $perLivello['official']);
        $this->assertContains('Lupi Santa Croce', $perLivello['official']);
        $this->assertContains('Volley Appenino', $perLivello['affiliated']);
        $this->assertNotContains('Volley Appenino', $perLivello['official']);
    }

    public function test_prende_il_sito_dall_ancora_che_avvolge_il_logo(): void
    {
        $nottolini = $this->societaChiamata('Nottolini Volley');

        $this->assertSame('https://www.facebook.com/nottolinivolley/', $nottolini['url']);
        $this->assertStringStartsWith('https://savinodelbenevolley.it/wp-content/uploads/', $nottolini['logo']);
    }

    /**
     * Le immagini senza alt sono il marchio in testata, i riquadri del menu e
     * i pixel di tracciamento: in elenco diventerebbero societa' senza nome.
     */
    public function test_ignora_le_immagini_senza_alt_e_quelle_prima_del_primo_titolo(): void
    {
        foreach ($this->societa as $voce) {
            $this->assertNotSame('', trim($voce['name']));
        }

        $this->assertNull($this->cerca('Ticketing'));
        $this->assertNull($this->cerca('Merchandising'));
    }

    public function test_decodifica_gli_apostrofi_nel_nome(): void
    {
        $html = '<h2>Società Affiliate</h2><img src="/logo.png" alt="Castiglione d&apos;Orcia">';
        $societa = (new ParserDelleAffiliate)->analizza($html, 'https://savinodelbenevolley.it/affiliazioni/');

        $this->assertSame("Castiglione d'Orcia", $societa[0]['name']);
        $this->assertSame('https://savinodelbenevolley.it/logo.png', $societa[0]['logo']);
    }

    /**
     * @return array{name: string, tier: string, url: ?string, logo: string}
     */
    private function societaChiamata(string $nome): array
    {
        $trovata = $this->cerca($nome);
        $this->assertNotNull($trovata, "Societa' non riconosciuta: {$nome}");

        return $trovata;
    }

    /**
     * @return array{name: string, tier: string, url: ?string, logo: string}|null
     */
    private function cerca(string $nome): ?array
    {
        foreach ($this->societa as $voce) {
            if ($voce['name'] === $nome) {
                return $voce;
            }
        }

        return null;
    }
}
