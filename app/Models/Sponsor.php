<?php

namespace App\Models;

use App\Enums\SponsorTier;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Sponsor extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'name', 'url', 'tier', 'sort_order',
    ];

    public $translatable = ['description'];

    protected $casts = [
        'tier' => SponsorTier::class,
    ];
}
