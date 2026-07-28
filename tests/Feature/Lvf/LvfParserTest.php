<?php

namespace Tests\Feature\Lvf;

use App\Services\Lvf\LvfMatchParser;
use App\Services\Lvf\LvfStandingsParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I parser girano su fixture HTML reali, ritagliate dalle pagine della Lega e
 * salvate in tests/Fixtures/Lvf. Se la Lega cambia il markup questi test
 * falliscono soltanto dopo che le fixture sono state aggiornate: servono a
 * bloccare le regressioni nostre, non a sorvegliare il sito remoto.
 */
class LvfParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/Lvf/{$name}.html"));
    }

    #[Test]
    public function estrae_le_gare_gia_giocate_con_i_set(): void
    {
        $matches = (new LvfMatchParser)->parse($this->fixture('risultati-giocate'));

        $this->assertCount(4, $matches);

        $first = $matches[0];
        $this->assertSame(747608, $first->lvfMatchId);
        $this->assertSame('21/02/2026 20:30', $first->playedAt->format('d/m/Y H:i'));
        $this->assertSame(710936, $first->homeClubId);
        $this->assertSame(710910, $first->awayClubId);
        $this->assertSame(2, $first->homeSets);
        $this->assertSame(3, $first->awaySets);
        $this->assertTrue($first->isPlayed());
    }

    #[Test]
    public function estrae_le_gare_in_calendario_con_giornata_e_impianto(): void
    {
        $matches = (new LvfMatchParser)->parse($this->fixture('calendario-programmate'));

        $this->assertCount(4, $matches);

        $savino = collect($matches)->firstWhere('homeClubId', 710955);

        $this->assertNotNull($savino);
        $this->assertSame(1, $savino->matchday);
        $this->assertSame('Andata', $savino->phase);
        $this->assertSame('LVF A1 Fineco', $savino->competition);
        $this->assertSame('Pala BigMat - Firenze', $savino->location);
    }

    #[Test]
    public function una_gara_in_calendario_non_e_considerata_giocata(): void
    {
        $matches = (new LvfMatchParser)->parse($this->fixture('calendario-programmate'));

        foreach ($matches as $match) {
            $this->assertFalse(
                $match->isPlayed(),
                "La gara {$match->lvfMatchId} non è stata disputata ma risulta giocata."
            );
        }
    }

    #[Test]
    public function un_punteggio_zero_a_zero_non_conta_come_gara_giocata(): void
    {
        // La pagina risultati emette 0-0 per le gare ancora da disputare: senza
        // questa distinzione ogni gara futura verrebbe mostrata come sconfitta.
        $html = <<<'HTML'
            <table class="table risultati">
              <thead><tr>
                <th>#3002</th><th>03/10/2026</th><th>20:00</th><th>Allianz Cloud</th>
                <th><a href="https://www.legavolleyfemminile.it/match-center/747983/">MATCH CENTER</a></th>
              </tr></thead>
              <tbody>
                <tr><th class="num" scope="row">0</th><td><a href="/club/club/710951/">Numia Vero Volley Milano</a></td></tr>
                <tr><th class="num" scope="row">0</th><td><a href="/club/club/710949/">Il Bisonte Firenze</a></td></tr>
              </tbody>
            </table>
            HTML;

        $matches = (new LvfMatchParser)->parse($html);

        $this->assertCount(1, $matches);
        $this->assertFalse($matches[0]->isPlayed());
    }

    #[Test]
    public function scarta_le_gare_senza_link_al_match_center(): void
    {
        // Senza identificativo remoto non esiste chiave stabile per l'upsert:
        // importarla creerebbe un duplicato a ogni sincronizzazione.
        $html = <<<'HTML'
            <table class="table risultati">
              <thead><tr><th>#3002</th><th>03/10/2026</th><th>20:00</th></tr></thead>
              <tbody>
                <tr><td><a href="/club/club/710951/">Numia Vero Volley Milano</a></td></tr>
                <tr><td><a href="/club/club/710949/">Il Bisonte Firenze</a></td></tr>
              </tbody>
            </table>
            HTML;

        $this->assertSame([], (new LvfMatchParser)->parse($html));
    }

    #[Test]
    public function estrae_la_classifica_ignorando_la_griglia_degli_scontri_diretti(): void
    {
        // La pagina contiene due tabelle e il nome della seconda
        // ("griglia-classifica") contiene quello della prima come sottostringa.
        $rows = (new LvfStandingsParser)->parse($this->fixture('classifica'));

        $this->assertCount(14, $rows);

        $first = $rows[0];
        $this->assertSame(1, $first->position);
        $this->assertSame(710972, $first->clubId);
        $this->assertSame('Banca Valsabbina Millenium Brescia', $first->teamName);

        $positions = array_map(fn ($row) => $row->position, $rows);
        $this->assertSame(range(1, 14), $positions);
    }

    #[Test]
    public function mappa_le_colonne_di_classifica_dalle_intestazioni(): void
    {
        $html = <<<'HTML'
            <table class="table classifica">
              <thead><tr>
                <th>Pos</th><th>Squadra</th><th>PT</th><th>G</th><th>V</th><th>P</th>
                <th>3-0</th><th>3-1</th><th>3-2</th><th>2-3</th><th>1-3</th><th>0-3</th>
                <th>SV</th><th>SP</th><th>PF</th><th>PS</th><th>QS</th><th>QP</th>
              </tr></thead>
              <tbody><tr>
                <th class="num">1</th>
                <td><a href="/club/savino-del-bene-scandicci/710955/">Savino Del Bene Scandicci</a></td>
                <td>42</td><td>16</td><td>14</td><td>2</td>
                <td>9</td><td>4</td><td>1</td><td>1</td><td>1</td><td>0</td>
                <td>45</td><td>18</td><td>1500</td><td>1300</td><td>2.50</td><td>1.15</td>
              </tr></tbody>
            </table>
            HTML;

        $rows = (new LvfStandingsParser)->parse($html);

        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertSame(710955, $row->clubId);
        $this->assertSame(42, $row->points);
        $this->assertSame(16, $row->played);
        $this->assertSame(14, $row->won);
        $this->assertSame(2, $row->lost);
        $this->assertSame(9, $row->won30);
        $this->assertSame(45, $row->setsWon);
        $this->assertSame(18, $row->setsLost);
        $this->assertSame(1500, $row->pointsFor);
        $this->assertSame(1300, $row->pointsAgainst);
        $this->assertSame(2.5, $row->setRatio);
        $this->assertSame(1.15, $row->pointRatio);
    }

    #[Test]
    public function regge_un_riordino_delle_colonne_di_classifica(): void
    {
        // Le colonne sono lette dalle intestazioni, non per posizione.
        $html = <<<'HTML'
            <table class="table classifica">
              <thead><tr><th>Pos</th><th>Squadra</th><th>G</th><th>PT</th></tr></thead>
              <tbody><tr>
                <th class="num">3</th>
                <td><a href="/club/club/710949/">Il Bisonte Firenze</a></td>
                <td>16</td><td>28</td>
              </tr></tbody>
            </table>
            HTML;

        $rows = (new LvfStandingsParser)->parse($html);

        $this->assertSame(28, $rows[0]->points);
        $this->assertSame(16, $rows[0]->played);
    }

    #[Test]
    public function una_pagina_senza_tabelle_non_esplode(): void
    {
        $this->assertSame([], (new LvfMatchParser)->parse('<html><body><p>Manutenzione</p></body></html>'));
        $this->assertSame([], (new LvfStandingsParser)->parse('<html><body><p>Manutenzione</p></body></html>'));
    }
}
