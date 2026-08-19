<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\Ga4ReportAssembler;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'assemblatore è la parte del modulo che si rompe per prima: se Google
 * rinomina una metrica o cambia l'ordine delle righe, i numeri restano
 * plausibili ma sbagliati, e nessuno se ne accorge guardando la pagina.
 *
 * Questi test fissano il contratto con GA4 riga per riga, senza toccare la rete.
 */
class Ga4ReportAssemblerTest extends TestCase
{
    #[Test]
    public function chiede_otto_report_fra_cui_le_pagine_viste(): void
    {
        $requests = Ga4ReportAssembler::buildRequests(28);

        $this->assertCount(8, $requests);

        // Il report delle pagine è quello che dà senso all'intera schermata:
        // deve chiedere il percorso, non solo il titolo.
        $dimensions = array_column($requests[2]['dimensions'], 'name');
        $this->assertSame(['pagePath', 'pageTitle'], $dimensions);
        $this->assertSame(['screenPageViews', 'activeUsers', 'averageSessionDuration'], array_column($requests[2]['metrics'], 'name'));
    }

    #[Test]
    public function il_primo_report_confronta_periodo_corrente_e_precedente(): void
    {
        $requests = Ga4ReportAssembler::buildRequests(7);

        // Senza il secondo intervallo non ci sarebbe niente con cui calcolare i
        // delta, e le variazioni mostrate sarebbero inventate.
        $this->assertCount(2, $requests[0]['dateRanges']);
        $this->assertSame(['startDate' => '6daysAgo', 'endDate' => 'today'], $requests[0]['dateRanges'][0]);
        $this->assertSame(['startDate' => '13daysAgo', 'endDate' => '7daysAgo'], $requests[0]['dateRanges'][1]);
    }

    #[Test]
    public function distingue_i_totali_del_periodo_corrente_da_quelli_del_precedente(): void
    {
        $reports = $this->reports([
            0 => [
                ['dims' => ['date_range_0'], 'metrics' => [100, 40, 120, 300, 65.4, 0.55, 0.31, 900, 66, 7848]],
                ['dims' => ['date_range_1'], 'metrics' => [50, 20, 60, 150, 60.0, 0.50, 0.35, 400, 30, 3600]],
            ],
        ]);

        $data = Ga4ReportAssembler::assemble($reports, 7);

        $this->assertSame(100, $data['totals']['active_users']);
        $this->assertSame(50, $data['previous']['active_users']);
        // Il raddoppio deve leggersi come +100%, non come +50 utenti.
        $this->assertSame(100.0, $data['deltas']['active_users']);
        // GA4 dà i tassi come frazione: in pagina vanno in percentuale.
        $this->assertSame(55.0, $data['totals']['engagement_rate']);
    }

    #[Test]
    public function riempie_di_zeri_i_giorni_senza_traffico(): void
    {
        $today = Ga4ReportAssembler::today();
        $ieri = $today->modify('-1 day')->format('Ymd');

        $reports = $this->reports([
            1 => [
                ['dims' => [$ieri], 'metrics' => [5, 3, 6, 12, 4, 300]],
            ],
        ]);

        $data = Ga4ReportAssembler::assemble($reports, 3);

        // Tre giorni chiesti, tre giorni restituiti: un grafico con i buchi si
        // legge peggio di uno con gli zeri.
        $this->assertCount(3, $data['daily']);
        $this->assertSame($today->modify('-2 days')->format('Y-m-d'), $data['daily'][0]['day']);
        $this->assertSame(0, $data['daily'][0]['active_users']);
        $this->assertSame(5, $data['daily'][1]['active_users']);
        $this->assertSame(0, $data['daily'][2]['active_users']);
    }

    #[Test]
    public function normalizza_le_ripartizioni_e_le_etichette_vuote(): void
    {
        $reports = $this->reports([
            3 => [['dims' => ['Organic Search'], 'metrics' => [80, 60]]],
            5 => [['dims' => [''], 'metrics' => [10, 12]]],
            6 => [['dims' => ['', 'Italy'], 'metrics' => [4]]],
            7 => [['dims' => ['/'], 'metrics' => [30, 0.42]]],
        ]);

        $data = Ga4ReportAssembler::assemble($reports, 7);

        $this->assertSame(['name' => 'Organic Search', 'sessions' => 80, 'users' => 60], $data['channels'][0]);
        // Una dimensione vuota non deve diventare una riga senza etichetta.
        $this->assertSame('(non impostato)', $data['devices'][0]['name']);
        $this->assertSame('(sconosciuta)', $data['cities'][0]['city']);
        $this->assertSame(42.0, $data['landing'][0]['engagement_rate']);
    }

    #[Test]
    public function un_periodo_precedente_a_zero_non_produce_una_variazione(): void
    {
        // Dividere per zero darebbe "+∞%": meglio non mostrare nulla.
        $this->assertNull(Ga4ReportAssembler::delta(10, 0));
        $this->assertSame(-50.0, Ga4ReportAssembler::delta(5, 10));
    }

    #[Test]
    public function regge_una_risposta_senza_righe(): void
    {
        // Una property nuova, o un periodo senza traffico, non è un errore.
        $data = Ga4ReportAssembler::assemble([], 7);

        $this->assertSame(0, $data['totals']['active_users']);
        $this->assertCount(7, $data['daily']);
        $this->assertSame([], $data['pages']);
    }

    /**
     * @param  array<int, list<array{dims: list<string>, metrics: list<float>}>>  $rowsByIndex
     * @return list<array{rows: list<array{dims: list<string>, metrics: list<float>}>}>
     */
    private function reports(array $rowsByIndex): array
    {
        $reports = [];

        for ($i = 0; $i < 8; $i++) {
            $reports[] = ['rows' => $rowsByIndex[$i] ?? []];
        }

        return $reports;
    }
}
