<?php

namespace App\Services\Lvf\Data;

/**
 * Il tabellino completo di una gara: dati di referto e statistiche individuali
 * di entrambe le squadre.
 */
class LvfBoxScore
{
    /**
     * @param  list<LvfPlayerStat>  $players
     * @param  list<array{set: int, duration: int|null, partials: list<string>}>  $sets
     */
    public function __construct(
        public readonly int $lvfMatchId,
        public readonly array $players = [],
        public readonly array $sets = [],
        public readonly ?int $spectators = null,
        public readonly ?string $referees = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->players === [];
    }

    /**
     * @return list<LvfPlayerStat>
     */
    public function playersOfClub(int $clubId): array
    {
        return array_values(array_filter(
            $this->players,
            fn (LvfPlayerStat $player) => $player->clubId === $clubId
        ));
    }
}
