<?php

namespace App\Models;

use App\Enums\SponsorTier;
use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Sponsor extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'name', 'url', 'tier', 'sort_order',
    ];

    // La tabella `sponsors` non ha una colonna `description`.
    public $translatable = [];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tier' => SponsorTier::class,
        ];
    }

    /**
     * Gli spot e le cornici della diretta.
     *
     * @return HasMany<LiveCreative, $this>
     */
    public function liveCreatives(): HasMany
    {
        return $this->hasMany(LiveCreative::class);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions();
    }
}
