<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = [
        'first_name', 'last_name', 'date_of_birth', 
        'nationality', 'instagram_handle', 'lega_volley_id'
    ];

    public function rosters()
    {
        return $this->hasMany(Roster::class);
    }
    
    public function stats()
    {
        return $this->hasMany(PlayerStat::class);
    }
}
