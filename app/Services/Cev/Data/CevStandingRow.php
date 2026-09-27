<?php

namespace App\Services\Cev\Data;

/**
 * Una riga della classifica di un girone.
 */
class CevStandingRow
{
    public function __construct(
        public readonly string $girone,
        public readonly int $position,
        public readonly string $teamName,
        public readonly int $played,
        public readonly int $won,
        public readonly int $lost,
        public readonly int $won30,
        public readonly int $won31,
        public readonly int $won32,
        public readonly int $lost23,
        public readonly int $lost13,
        public readonly int $lost03,
        public readonly int $points,
        public readonly int $setsWon,
        public readonly int $setsLost,
        public readonly float $setRatio,
        public readonly int $pointsFor,
        public readonly int $pointsAgainst,
        public readonly float $pointRatio,
    ) {}
}
