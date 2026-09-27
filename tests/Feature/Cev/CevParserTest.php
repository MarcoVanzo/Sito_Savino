<?php

namespace Tests\Feature\Cev;

use App\Services\Cev\CevMatchParser;
use App\Services\Cev\CevStandingsParser;
use App\Services\Cev\Data\CevMatch;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I parser del portale CEV su pagine vere, salvate in tests/Fixtures/Cev:
 * la Champions League femminile 2025/26 (ID 1802, già giocata) e la 2026/27
 * (ID 1948, in calendario). Se la CEV cambia il markup, si aggiornano le
 * fixture e poi i parser.
 */
class CevParserTest extends TestCase
{
    private function fixture(string $nome): string
    {
        return (string) file_get_contents(base_path("tests/Fixtures/Cev/{$nome}.html"));
    }

    private function parser(): CevMatchParser
    {
        return CevMatchParser::fromConfig();
    }

    /**
     * @param  list<CevMatch>  $gare
     */
    private function gara(array $gare, string $codice): CevMatch
    {
        foreach ($gare as $gara) {
            if ($gara->code === $codice) {
                return $gara;
            }
        }

        $this->fail("Gara {$codice} non trovata.");
    }

    #[Test]
    public function legge_le_fasi_della_competizione_nell_ordine_del_portale(): void
    {
        $this->assertSame([3534, 3535, 3537, 3538, 3539], $this->parser()->phaseIds($this->fixture('competizione-1948'), 1948));
        $this->assertSame([3340, 3341, 3342, 3343, 3344], $this->parser()->phaseIds($this->fixture('competizione-1802'), 1802));
    }

    #[Test]
    public function legge_le_gare_dei_gironi_con_risultato_giornata_e_girone(): void
    {
        $gare = $this->parser()->parse($this->fixture('partite-1802-gironi'));

        $this->assertCount(60, $gare);
        $this->assertCount(60, array_filter($gare, fn (CevMatch $g) => $g->isPlayed()));

        $gara = $this->gara($gare, 'CLVW 18');
        $this->assertSame('Savino Del Bene SCANDICCI', $gara->homeName);
        $this->assertSame(14181, $gara->homeTeamId);
        $this->assertSame('CS Volei Alba BLAJ', $gara->awayName);
        $this->assertSame([3, 0], [$gara->homeSets, $gara->awaySets]);
        $this->assertSame('Pool A', $gara->phase);
        $this->assertSame(1, $gara->matchday);
        $this->assertSame('Palazzo Wanny FIRENZE', $gara->location);
        $this->assertSame('2025-11-27 20:00', $gara->playedAt?->format('Y-m-d H:i'));
    }

    #[Test]
    public function l_ora_locale_del_palazzetto_diventa_ora_italiana(): void
    {
        // Vakifbank - Savino, a Istanbul: il portale scrive 19:30 ora turca,
        // che in Italia sono le 17:30 (d'inverno la Turchia è due ore avanti).
        $gara = $this->gara($this->parser()->parse($this->fixture('partite-1802-gironi')), 'CLVW 27');

        $this->assertStringEndsWith('ISTANBUL', (string) $gara->location);
        $this->assertSame('Europe/Rome', $gara->playedAt?->getTimezone()->getName());
        $this->assertSame('2026-02-04 17:30', $gara->playedAt?->format('Y-m-d H:i'));
    }

    #[Test]
    public function nei_turni_a_eliminazione_andata_e_ritorno_sono_parte_della_fase(): void
    {
        $gare = $this->parser()->parse($this->fixture('partite-1802-play-off'));
        $andata = $this->gara($gare, 'CLVW 81');
        $ritorno = $this->gara($gare, 'CLVW 82');

        $this->assertSame('Play Off · Home Matches', $andata->phase);
        $this->assertSame('Play Off · Away Matches', $ritorno->phase);
        $this->assertNull($andata->matchday);

        $semifinale = $this->gara($this->parser()->parse($this->fixture('partite-1802-final-four')), 'CLVW 92');
        $this->assertSame('Final Four · Semi Finals', $semifinale->phase);
    }

    #[Test]
    public function segnaposto_e_date_non_fissate_non_sono_gare_pubblicabili(): void
    {
        $gare = $this->parser()->parse($this->fixture('partite-1948-gironi'));

        $this->assertCount(60, $gare);
        $this->assertCount(0, array_filter($gare, fn (CevMatch $g) => $g->isPlayed()));

        // "Winner Matches 3rd Round CLVW 11/12": squadra con TeamID 0.
        $casa = $this->gara($gare, 'CLVW 49');
        $this->assertSame('Savino Del Bene SCANDICCI', $casa->homeName);
        $this->assertFalse($casa->hasTeams());
        $this->assertSame('2026-11-25 18:30', $casa->playedAt?->format('Y-m-d H:i'));

        // "Match not yet scheduled".
        $this->assertNull($this->gara($gare, 'CLVW 55')->playedAt);

        // Il segnaposto "01/01/2100 00:00" non è una data.
        foreach ($gare as $gara) {
            $this->assertTrue($gara->playedAt === null || $gara->playedAt->year < 2100, $gara->code);
        }
    }

    #[Test]
    public function legge_la_classifica_di_ogni_girone(): void
    {
        $righe = (new CevStandingsParser)->parse($this->fixture('classifica-1802-gironi'));

        $this->assertCount(20, $righe);
        $this->assertSame(['Pool A', 'Pool B', 'Pool C', 'Pool D', 'Pool E'], array_values(array_unique(array_map(fn ($r) => $r->girone, $righe))));

        $savino = array_values(array_filter($righe, fn ($r) => $r->teamName === 'Savino Del Bene SCANDICCI'))[0];
        $this->assertSame('Pool A', $savino->girone);
        $this->assertSame(2, $savino->position);
        $this->assertSame([6, 4, 2], [$savino->played, $savino->won, $savino->lost]);
        $this->assertSame([3, 1, 0, 1, 1, 0], [$savino->won30, $savino->won31, $savino->won32, $savino->lost23, $savino->lost13, $savino->lost03]);
        $this->assertSame(13, $savino->points);
        $this->assertSame([15, 7], [$savino->setsWon, $savino->setsLost]);
        $this->assertSame(2.1429, $savino->setRatio);
        $this->assertSame([521, 445], [$savino->pointsFor, $savino->pointsAgainst]);
        $this->assertSame(1.1708, $savino->pointRatio);
    }

    #[Test]
    public function una_classifica_ancora_da_giocare_ha_tutto_a_zero(): void
    {
        $righe = (new CevStandingsParser)->parse($this->fixture('classifica-1948-gironi'));
        $girone = array_values(array_filter($righe, fn ($r) => $r->girone === 'Pool D'));

        $this->assertSame(['Eczacibasi Peron ISTANBUL', 'Savino Del Bene SCANDICCI', 'Tent OBRENOVAC'], array_map(fn ($r) => $r->teamName, $girone));
        $this->assertSame(0.0, $girone[1]->setRatio);
        $this->assertSame(0, $girone[1]->points);
    }

    #[Test]
    public function legge_anno_e_sesso_dell_edizione(): void
    {
        $this->assertSame(['anno' => 2027, 'femminile' => true], $this->parser()->edizione($this->fixture('competizione-1948')));
        $this->assertSame(['anno' => 2026, 'femminile' => true], $this->parser()->edizione($this->fixture('competizione-1802')));
        $this->assertSame(['anno' => null, 'femminile' => false], $this->parser()->edizione('<html></html>'));
    }

    #[Test]
    public function senza_impianto_il_fuso_si_ricava_dalla_squadra_di_casa(): void
    {
        $html = str_replace('Vakifbank Spor Sarayi ISTANBUL', '', $this->fixture('partite-1802-gironi'));
        $gara = $this->gara($this->parser()->parse($html), 'CLVW 27');

        $this->assertNull($gara->location);
        $this->assertSame('2026-02-04 17:30', $gara->playedAt?->format('Y-m-d H:i'));
    }
}
