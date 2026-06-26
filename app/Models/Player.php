<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Player extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'first_name', 'last_name', 'date_of_birth', 
        'nationality', 'instagram_handle', 'lega_volley_id'
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function rosters()
    {
        return $this->hasMany(Roster::class);
    }
    
    public function stats()
    {
        return $this->hasMany(PlayerStat::class);
    }
}
