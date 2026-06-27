<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Traits\LogsActivity;

class Player extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'first_name', 'last_name', 'date_of_birth', 
        'nationality', 'instagram_handle', 'lega_volley_id',
        'is_staff', 'staff_role', 'photo_url', 'sort_order',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_staff' => 'boolean',
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
