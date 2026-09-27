<?php

namespace App\Services\Cev\Data;

use Carbon\CarbonImmutable;

/**
 * Una gara così come la pubblica il portale della CEV, prima di essere
 * mappata sui modelli.
 */
class CevMatch
{
    /**
     * @param  int  $cevMatchId  `mID` del portale: stabile, è la chiave dell'upsert
     * @param  int  $homeTeamId  `TeamID` della squadra sul portale; 0 per i segnaposto ("Winner CLVW 01/02")
     * @param  CarbonImmutable|null  $playedAt  già convertita nel fuso del sito; null se non ancora fissata
     * @param  string  $phase  girone o turno ("Pool D", "Play Off · Home Matches")
     * @param  int|null  $matchday  giornata del girone ("Leg 3" → 3); null nei turni a eliminazione
     */
    public function __construct(
        public readonly int $cevMatchId,
        public readonly string $code,
        public readonly int $homeTeamId,
        public readonly string $homeName,
        public readonly int $awayTeamId,
        public readonly string $awayName,
        public readonly ?CarbonImmutable $playedAt,
        public readonly ?string $location,
        public readonly string $phase,
        public readonly ?int $matchday,
        public readonly ?int $homeSets = null,
        public readonly ?int $awaySets = null,
    ) {}

    /**
     * Le due squadre sono note: finché una delle due è "la vincente di…" la
     * gara non si può ancora pubblicare.
     */
    public function hasTeams(): bool
    {
        return $this->homeTeamId > 0 && $this->awayTeamId > 0;
    }

    public function isPlayed(): bool
    {
        // Come per la Lega, 0-0 è il segnaposto delle gare non ancora giocate.
        return $this->homeSets !== null
            && $this->awaySets !== null
            && ($this->homeSets > 0 || $this->awaySets > 0);
    }
}
