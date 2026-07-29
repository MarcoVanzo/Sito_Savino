<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStat extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'player_id', 'season_id', 'team_id',
        'matches_played', 'sets_played',
        'points', 'points_break', 'blocks', 'aces',
        'serve_total', 'serve_errors',
        'attacks', 'attack_errors', 'attack_blocked', 'attack_points', 'attack_pct',
        'receptions', 'reception_errors', 'reception_positive_pct', 'reception_perfect_pct',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Punti per set giocato: il modo consueto di confrontare atlete che hanno
     * disputato un numero diverso di partite.
     */
    public function pointsPerSet(): ?float
    {
        return $this->sets_played > 0
            ? round($this->points / $this->sets_played, 2)
            : null;
    }
}
