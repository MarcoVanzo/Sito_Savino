<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\Ga4Client;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La traduzione degli errori di Google decide cosa legge chi apre la pagina.
 * "403" non aiuta nessuno; "aggiungi il service account come Visualizzatore"
 * sì — ma solo se il 403 giusto finisce nella causa giusta.
 */
class Ga4ClientTest extends TestCase
{
    #[Test]
    public function distingue_il_permesso_mancante_dalla_api_non_abilitata(): void
    {
        // Entrambi sono 403 e vogliono due interventi completamente diversi:
        // uno si risolve in Google Analytics, l'altro nella console Google Cloud.
        $this->assertSame('not_authorized', Ga4Client::reasonFor(403, json_encode([
            'error' => ['message' => 'User does not have sufficient permissions for this property.'],
        ])));

        $this->assertSame('api_disabled', Ga4Client::reasonFor(403, json_encode([
            'error' => ['message' => 'Google Analytics Data API has not been used in project 1234 before or it is disabled.'],
        ])));
    }

    #[Test]
    public function riconosce_quota_property_inesistente_e_token_rifiutato(): void
    {
        $this->assertSame('quota', Ga4Client::reasonFor(429, '{}'));
        $this->assertSame('quota', Ga4Client::reasonFor(400, json_encode(['error' => ['status' => 'RESOURCE_EXHAUSTED']])));
        $this->assertSame('bad_property', Ga4Client::reasonFor(404, '{}'));
        $this->assertSame('auth_failed', Ga4Client::reasonFor(401, '{}'));
        $this->assertSame('unavailable', Ga4Client::reasonFor(503, 'gateway'));
    }

    #[Test]
    public function appiattisce_le_righe_di_un_report(): void
    {
        $parsed = Ga4Client::parseReport([
            'rows' => [
                [
                    'dimensionValues' => [['value' => '/news'], ['value' => 'Notizie']],
                    'metricValues' => [['value' => '120'], ['value' => '88']],
                ],
            ],
            'rowCount' => 1,
        ]);

        $this->assertSame([['dims' => ['/news', 'Notizie'], 'metrics' => [120.0, 88.0]]], $parsed['rows']);
        $this->assertSame(1, $parsed['row_count']);
    }

    #[Test]
    public function un_report_senza_righe_non_e_un_errore(): void
    {
        $parsed = Ga4Client::parseReport([]);

        $this->assertSame([], $parsed['rows']);
        $this->assertSame(0, $parsed['row_count']);
    }
}
