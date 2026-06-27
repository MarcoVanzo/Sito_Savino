<?php

namespace App\Models;

use App\Enums\SponsorTier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Sponsor extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name', 'url', 'tier', 'sort_order',
    ];

    protected $casts = [
        'tier' => SponsorTier::class,
    ];
}
