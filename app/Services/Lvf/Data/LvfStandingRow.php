<?php

namespace App\Services\Lvf\Data;

/**
 * Una riga di classifica pubblicata dalla Lega.
 */
class LvfStandingRow
{
    public function __construct(
        public readonly int $position,
        public readonly int $clubId,
        public readonly string $teamName,
        public readonly int $points,
        public readonly int $played,
        public readonly int $won,
        public readonly int $lost,
        public readonly int $won30,
        public readonly int $won31,
        public readonly int $won32,
        public readonly int $lost23,
        public readonly int $lost13,
        public readonly int $lost03,
        public readonly int $setsWon,
        public readonly int $setsLost,
        public readonly int $pointsFor,
        public readonly int $pointsAgainst,
        public readonly ?float $setRatio = null,
        public readonly ?float $pointRatio = null,
    ) {}
}
