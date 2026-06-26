<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Roster extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'player_id', 'team_id', 'season_id',
        'jersey_number', 'role', 'height_cm', 'is_captain',
        'bio'
    ];

    protected $casts = [
        'is_captain' => 'boolean',
        'height_cm' => 'integer',
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
