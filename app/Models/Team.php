<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = ['name', 'slug', 'category'];

    public function rosters()
    {
        return $this->hasMany(Roster::class);
    }
}
