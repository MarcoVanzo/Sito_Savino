<?php

namespace App\Models;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'season_id', 'home_team_id', 'away_team_id',
        'match_date', 'status', 'home_score', 'away_score',
        'location', 'competition_type',
    ];

    protected $casts = [
        'match_date' => 'datetime',
        'status' => GameStatus::class,
        'competition_type' => CompetitionType::class,
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
