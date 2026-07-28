<?php

namespace App\Services\Lvf\Data;

use Carbon\CarbonImmutable;

/**
 * Una gara così come la pubblica la Lega, prima di essere mappata sui modelli.
 */
class LvfMatch
{
    /**
     * @param  int  $lvfMatchId  identificativo del Match Center, stabile fra le stagioni
     * @param  int|null  $homeSets  set vinti in casa; null se la gara non è ancora stata giocata
     */
    public function __construct(
        public readonly int $lvfMatchId,
        public readonly ?string $code,
        public readonly CarbonImmutable $playedAt,
        public readonly int $homeClubId,
        public readonly string $homeName,
        public readonly int $awayClubId,
        public readonly string $awayName,
        public readonly ?int $homeSets = null,
        public readonly ?int $awaySets = null,
        public readonly ?string $location = null,
        public readonly ?int $matchday = null,
        public readonly ?string $phase = null,
        public readonly ?string $competition = null,
    ) {}

    public function isPlayed(): bool
    {
        // Una gara di pallavolo non può chiudersi 0-0: la Lega usa quel valore
        // come segnaposto per le gare in calendario ma non ancora disputate.
        return $this->homeSets !== null
            && $this->awaySets !== null
            && ($this->homeSets > 0 || $this->awaySets > 0);
    }

    /**
     * @return array{0: int, 1: string}[] coppie (id club, nome) nell'ordine casa, ospite
     */
    public function clubs(): array
    {
        return [
            [$this->homeClubId, $this->homeName],
            [$this->awayClubId, $this->awayName],
        ];
    }
}
