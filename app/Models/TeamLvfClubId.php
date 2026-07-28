<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un identificativo di club usato dalla Lega per una squadra.
 *
 * Ce n'è uno per stagione: la Lega li rinumera ogni anno.
 */
class TeamLvfClubId extends Model
{
    protected $table = 'team_lvf_club_ids';

    protected $fillable = ['team_id', 'lvf_club_id', 'season_year'];

    protected $casts = [
        'lvf_club_id' => 'integer',
        'season_year' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
