<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerStat extends Model
{
    protected $fillable = [
        'player_id', 'season_id',
        'points', 'blocks', 'aces', 'attacks', 'receptions',
        'last_synced_at'
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
