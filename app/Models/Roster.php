<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roster extends Model
{
    protected $fillable = [
        'player_id', 'team_id', 'season_id',
        'jersey_number', 'role', 'height_cm', 'is_captain',
        'official_photo_url', 'action_photo_url', 'bio'
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
