<?php

namespace App\Models;

use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class HeroSlide extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'title',
        'subtitle',
        'cta_text',
        'cta_url',
        'sort_order',
        'is_active',
    ];

    public $translatable = ['title', 'subtitle', 'cta_text'];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Register media collections for hero slide images.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero-slides')->singleFile();
    }

    /**
     * Scope: only active slides.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: ordered by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions();

        $this->addMediaConversion('hero')
            ->width(1920)
            ->quality(85);

        // Per i telefoni: la `hero` da 1920 px in JPEG pesava 300-450 KB ed era
        // la prima immagine della home anche su uno schermo da 412 px (audit
        // del 20/10/2026). Nuova accanto a `hero`, non al suo posto: cambiare
        // il formato di `hero` avrebbe puntato a file mai generati.
        $this->addMediaConversion('hero_mobile')
            ->width(1200)
            ->format('webp')
            ->quality(75);
    }

    /**
     * Le due misure dell'immagine dell'hero: `mobile` e' null finche' la
     * conversione non e' stata generata (si usa allora solo la grande).
     *
     * @return array{grande: string, mobile: string|null}
     */
    public function immaginiDellHero(): array
    {
        $media = $this->getFirstMedia('hero-slides');

        if ($media === null) {
            return ['grande' => '/images/hero1.jpg', 'mobile' => null];
        }

        return [
            'grande' => $media->hasGeneratedConversion('hero') ? $media->getUrl('hero') : $media->getUrl(),
            'mobile' => $media->hasGeneratedConversion('hero_mobile') ? $media->getUrl('hero_mobile') : null,
        ];
    }
}
