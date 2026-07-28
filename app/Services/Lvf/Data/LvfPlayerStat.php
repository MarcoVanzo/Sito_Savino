<?php

namespace App\Services\Lvf\Data;

/**
 * Riga del tabellino: i numeri di una giocatrice in una singola gara.
 */
class LvfPlayerStat
{
    public function __construct(
        public readonly int $clubId,
        public readonly string $playerName,
        public readonly ?int $jerseyNumber = null,
        public readonly bool $isCaptain = false,
        public readonly bool $isLibero = false,
        public readonly int $setsPlayed = 0,
        public readonly int $pointsTotal = 0,
        public readonly int $pointsBreak = 0,
        public readonly int $pointsWinLoss = 0,
        public readonly int $serveTotal = 0,
        public readonly int $serveErrors = 0,
        public readonly int $servePoints = 0,
        public readonly int $receptionTotal = 0,
        public readonly int $receptionErrors = 0,
        public readonly ?int $receptionPositivePct = null,
        public readonly ?int $receptionPerfectPct = null,
        public readonly int $attackTotal = 0,
        public readonly int $attackErrors = 0,
        public readonly int $attackBlocked = 0,
        public readonly int $attackPoints = 0,
        public readonly ?int $attackPct = null,
        public readonly int $blockPoints = 0,
    ) {}

    /**
     * Una giocatrice a referto ma mai entrata non ha alcun dato utile.
     */
    public function playedAnySet(): bool
    {
        return $this->setsPlayed > 0;
    }
}
