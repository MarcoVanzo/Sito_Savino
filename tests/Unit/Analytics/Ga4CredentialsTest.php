<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\Ga4Credentials;
use App\Services\Analytics\Ga4Exception;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il service account arriva da una variabile d'ambiente, cioè dal punto del
 * sistema dove è più facile che un JSON si tronchi o perda gli a capo. Tutti i
 * modi in cui può arrivare rotto devono finire in un errore dichiarato, mai in
 * un 500: la pagina deve poter dire "il service account non va bene".
 */
class Ga4CredentialsTest extends TestCase
{
    #[Test]
    public function accetta_il_json_anche_in_base64(): void
    {
        // I secret di App Platform si passano volentieri in base64: un JSON con
        // gli a capo dentro una variabile d'ambiente è una fonte di guai.
        $json = json_encode(['client_email' => 'sa@progetto.iam.gserviceaccount.com', 'private_key' => 'chiave']);

        $this->assertSame('sa@progetto.iam.gserviceaccount.com', Ga4Credentials::fromJson($json)?->clientEmail);
        $this->assertSame('sa@progetto.iam.gserviceaccount.com', Ga4Credentials::fromJson(base64_encode($json))?->clientEmail);
    }

    #[Test]
    public function un_json_rotto_o_incompleto_non_produce_credenziali(): void
    {
        $this->assertNull(Ga4Credentials::fromJson('{non-json'));
        $this->assertNull(Ga4Credentials::fromJson(json_encode(['client_email' => 'sa@progetto.iam.gserviceaccount.com'])));
        $this->assertNull(Ga4Credentials::fromJson(json_encode(['private_key' => 'chiave'])));
    }

    #[Test]
    public function una_chiave_privata_illeggibile_diventa_un_errore_dichiarato(): void
    {
        config()->set('services.ga4.service_account_json', json_encode([
            'client_email' => 'sa@progetto.iam.gserviceaccount.com',
            'private_key' => 'questa-non-e-una-chiave',
        ]));

        $credentials = Ga4Credentials::fromConfig();
        $this->assertNotNull($credentials);

        // openssl_sign su una chiave malformata lancia: senza la rete di
        // protezione la pagina andrebbe in 500 invece di spiegare cosa manca.
        $this->expectException(Ga4Exception::class);
        $this->expectExceptionMessage('Chiave privata del service account non leggibile');

        $credentials->accessToken();
    }
}
