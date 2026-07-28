<?php

namespace App\Models;

use App\Enums\CompetitionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riga di classifica importata dal sito della Lega.
 *
 * I dati sono di sola lettura per il CMS: vengono riscritti a ogni
 * sincronizzazione, quindi una modifica manuale andrebbe persa.
 */
class Standing extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id', 'team_id', 'competition_type', 'position',
        'points', 'played', 'won', 'lost',
        'won_3_0', 'won_3_1', 'won_3_2', 'lost_2_3', 'lost_1_3', 'lost_0_3',
        'sets_won', 'sets_lost', 'points_for', 'points_against',
        'set_ratio', 'point_ratio', 'synced_at',
    ];

    protected $casts = [
        'competition_type' => CompetitionType::class,
        'synced_at' => 'datetime',
        'set_ratio' => 'float',
        'point_ratio' => 'float',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }
}
