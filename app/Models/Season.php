<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $fillable = ['name', 'is_current'];

    public function rosters()
    {
        return $this->hasMany(Roster::class);
    }
}
