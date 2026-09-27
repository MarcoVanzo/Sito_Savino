<?php

namespace App\Models;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Le due squadre sono null quando la squadra è archiviata: Team usa
 * SoftDeletes. Le chiavi esterne sono NOT NULL, quindi senza annotazione
 * PHPStan concluderebbe che la relazione c'è sempre.
 *
 * @property-read Team|null $homeTeam
 * @property-read Team|null $awayTeam
 */
class Game extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'season_id', 'home_team_id', 'away_team_id',
        'match_date', 'status', 'home_score', 'away_score',
        'location', 'stream_url', 'competition_type',
        'lvf_match_id', 'cev_match_id', 'matchday', 'phase', 'lvf_synced_at',
        'spectators', 'referees', 'set_scores', 'stats_synced_at',
    ];

    /**
     * Il sync della Lega gira ogni ora e riscrive questi due timestamp anche
     * quando nulla è cambiato: senza escluderli il registro attività si
     * riempiva di una riga per gara a ogni giro (16.285 righe in produzione,
     * tutte di sistema), seppellendo le azioni della redazione.
     */
    protected array $logExclude = ['lvf_synced_at', 'stats_synced_at'];

    protected $casts = [
        'match_date' => 'datetime',
        'status' => GameStatus::class,
        'competition_type' => CompetitionType::class,
        'lvf_synced_at' => 'datetime',
        'stats_synced_at' => 'datetime',
        'lvf_match_id' => 'integer',
        'matchday' => 'integer',
        'set_scores' => 'array',
    ];

    /**
     * @return HasMany<GamePlayerStat, $this>
     */
    public function playerStats(): HasMany
    {
        return $this->hasMany(GamePlayerStat::class);
    }

    /**
     * Le gare importate (dalla Lega o dalla CEV) non vanno modificate a mano
     * dal CMS: la sincronizzazione successiva sovrascriverebbe data, squadre,
     * luogo e risultato. Resta della redazione il link della diretta.
     */
    public function isImported(): bool
    {
        return $this->importataDallaLega() || $this->importataDallaCev();
    }

    public function importataDallaLega(): bool
    {
        return $this->lvf_match_id !== null;
    }

    public function importataDallaCev(): bool
    {
        return $this->cev_match_id !== null;
    }

    /**
     * Chi scrive questa gara, per i messaggi del pannello: "Lega" o "CEV".
     */
    public function fonteDellaSincronizzazione(): ?string
    {
        return match (true) {
            $this->importataDallaLega() => 'Lega',
            $this->importataDallaCev() => 'CEV',
            default => null,
        };
    }

    /**
     * La pagina della gara sul portale della CEV, da cui arrivano i dati.
     */
    public function indirizzoSulPortaleCev(): ?string
    {
        if (! $this->importataDallaCev()) {
            return null;
        }

        return rtrim((string) config('services.cev.base_url'), '/').'/MatchPage.aspx?mID='.$this->cev_match_id;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
