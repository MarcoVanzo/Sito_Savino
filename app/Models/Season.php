<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    protected $fillable = ['name', 'is_current'];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    /**
     * Scope: stagione corrente.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(Roster::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
