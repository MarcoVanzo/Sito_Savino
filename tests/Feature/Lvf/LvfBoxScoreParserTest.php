<?php

namespace Tests\Feature\Lvf;

use App\Services\Lvf\Data\LvfBoxScore;
use App\Services\Lvf\LvfBoxScoreParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il tabellino è una pagina ASP con tabelle annidate: ogni tabella delle
 * giocatrici è avvolta da contenitori che ripetono la stessa intestazione.
 * Questi test bloccano le due insidie che ne derivano — righe delle tabelle
 * interne raccolte per sbaglio, e squadre scambiate fra loro.
 */
class LvfBoxScoreParserTest extends TestCase
{
    private const HOME_CLUB = 710936;

    private const AWAY_CLUB = 710910;

    private function boxScore(): LvfBoxScore
    {
        return (new LvfBoxScoreParser)->parse(
            file_get_contents(base_path('tests/Fixtures/Lvf/tabellino.html')),
            747608,
            self::HOME_CLUB,
            self::AWAY_CLUB,
        );
    }

    #[Test]
    public function estrae_le_giocatrici_di_entrambe_le_squadre(): void
    {
        $box = $this->boxScore();

        $this->assertCount(28, $box->players);
        $this->assertCount(14, $box->playersOfClub(self::HOME_CLUB));
        $this->assertCount(14, $box->playersOfClub(self::AWAY_CLUB));
    }

    #[Test]
    public function non_attribuisce_le_giocatrici_alla_squadra_sbagliata(): void
    {
        // Le tabelle-contenitore hanno la stessa intestazione di quelle vere: se
        // vengono scambiate per tabelle di dati, consumano l'identificativo del
        // club e le atlete finiscono assegnate agli avversari.
        $box = $this->boxScore();

        $home = array_map(fn ($p) => $p->playerName, $box->playersOfClub(self::HOME_CLUB));
        $away = array_map(fn ($p) => $p->playerName, $box->playersOfClub(self::AWAY_CLUB));

        $this->assertContains('Zhuang Yushan', $home);
        $this->assertContains('Battista Valeria', $away);
        $this->assertNotContains('Battista Valeria', $home);
        $this->assertNotContains('Zhuang Yushan', $away);
    }

    #[Test]
    public function legge_le_statistiche_complete_di_una_giocatrice(): void
    {
        $box = $this->boxScore();

        $player = collect($box->playersOfClub(self::HOME_CLUB))->firstWhere('playerName', 'Zhuang Yushan');

        $this->assertNotNull($player);
        $this->assertSame(5, $player->jerseyNumber);
        $this->assertSame(5, $player->setsPlayed);
        $this->assertSame(29, $player->pointsTotal);
        $this->assertSame(11, $player->pointsBreak);
        $this->assertSame(17, $player->pointsWinLoss);
        $this->assertSame(21, $player->serveTotal);
        $this->assertSame(1, $player->serveErrors);
        $this->assertSame(31, $player->receptionTotal);
        $this->assertSame(4, $player->receptionErrors);
        $this->assertSame(55, $player->receptionPositivePct);
        $this->assertSame(29, $player->receptionPerfectPct);
        $this->assertSame(63, $player->attackTotal);
        $this->assertSame(5, $player->attackErrors);
        $this->assertSame(2, $player->attackBlocked);
        $this->assertSame(27, $player->attackPoints);
        $this->assertSame(43, $player->attackPct);
        $this->assertSame(2, $player->blockPoints);
    }

    #[Test]
    public function riconosce_capitano_e_libero_ripulendo_il_nome(): void
    {
        $box = $this->boxScore();
        $players = collect($box->players);

        $captain = $players->firstWhere('playerName', 'Ortolani Serena');
        $this->assertNotNull($captain, 'La sigla (C) non è stata rimossa dal nome.');
        $this->assertTrue($captain->isCaptain);

        $libero = $players->firstWhere('playerName', 'Pelloni Federica');
        $this->assertNotNull($libero, 'La sigla (L) non è stata rimossa dal nome.');
        $this->assertTrue($libero->isLibero);
    }

    #[Test]
    public function una_giocatrice_mai_entrata_ha_zero_set(): void
    {
        $box = $this->boxScore();

        $unused = collect($box->players)->firstWhere('playerName', 'Parini Sveva');

        $this->assertNotNull($unused);
        $this->assertSame(0, $unused->setsPlayed);
        $this->assertFalse($unused->playedAnySet());
    }

    #[Test]
    public function estrae_parziali_e_durata_dei_set(): void
    {
        // I parziali stanno in una tabella separata da quelle delle giocatrici:
        // vanno riconosciuti senza farsi ingannare dal contenitore più esterno,
        // che ingloba l'intero documento in un'unica riga.
        $box = $this->boxScore();

        $this->assertCount(5, $box->sets);
        $this->assertSame(1, $box->sets[0]['set']);
        $this->assertSame(28, $box->sets[0]['duration']);
        $this->assertSame(['8-7', '16-14', '18-21', '23-25'], $box->sets[0]['partials']);
        $this->assertSame(31, $box->sets[4]['duration']);
    }

    #[Test]
    public function estrae_spettatori_e_arbitri(): void
    {
        $box = $this->boxScore();

        // Il numero usa il punto come separatore delle migliaia: "1.444".
        $this->assertSame(1444, $box->spectators);
        $this->assertSame('Michele Brunelli - Rocco Brancati', $box->referees);
    }

    #[Test]
    public function un_tabellino_non_ancora_pubblicato_non_esplode(): void
    {
        $box = (new LvfBoxScoreParser)->parse('<html><body><p>Tabellino non disponibile</p></body></html>', 1, 2, 3);

        $this->assertTrue($box->isEmpty());
        $this->assertSame([], $box->sets);
        $this->assertNull($box->spectators);
    }
}
