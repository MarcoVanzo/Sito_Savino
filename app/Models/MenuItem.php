<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MenuItem extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'label',
        'url',
        'description',
        'motto_title',
        'motto_subtitle',
        'parent_id',
        'location',
        'sort_order',
        'is_active',
        'is_highlight',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_highlight' => 'boolean',
        'sort_order' => 'integer',
    ];

    private const CACHE_KEY = 'menu_items';
    private const CACHE_TTL = 86400;

    /**
     * Parent menu item.
     */
    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * Children menu items.
     */
    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /**
     * Register media collections for menu images.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('menu-images')->singleFile();
    }

    /**
     * Mappa label → file immagine statica in public/images/menu/.
     * Usata come fonte primaria; fallback alla media library Spatie.
     */
    private static array $staticMenuImages = [
        'Stagione' => 'stagione.jpg',
        'Società' => 'societa.jpg',
        'Ticketing' => 'ticketing.jpg',
        'Sponsor' => 'sponsor.jpg',
        'SDB Youth' => 'youth.jpg',
        'Camp' => 'camp.jpg',
        'Sociale' => 'sociale.jpg',
        'Media' => 'media.jpg',
        'Shop' => 'shop.jpg',
    ];

    /**
     * Get the full navigation tree for a location, cached.
     */
    public static function getTree(string $location = 'main'): array
    {
        return Cache::remember(self::CACHE_KEY . '_' . $location, self::CACHE_TTL, function () use ($location) {
            return static::where('location', $location)
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->with(['children'])
                ->orderBy('sort_order')
                ->get()
                ->map(function ($item) {
                    $menuImage = $item->getFirstMediaUrl('menu-images') ?: null;
                    if (!$menuImage && isset(self::$staticMenuImages[$item->label])) {
                        $menuImage = '/images/menu/' . self::$staticMenuImages[$item->label];
                    }

                    return [
                        'id' => $item->id,
                        'label' => $item->label,
                        'href' => $item->url,
                        'description' => $item->description,
                        'mottoTitle' => $item->motto_title,
                        'mottoSubtitle' => $item->motto_subtitle,
                        'menuImage' => $menuImage,
                        'isHighlight' => $item->is_highlight,
                        'children' => $item->children->map(function ($child) {
                            return [
                                'id' => $child->id,
                                'label' => $child->label,
                                'href' => $child->url,
                                'description' => $child->description,
                                'isHighlight' => $child->is_highlight,
                            ];
                        })->toArray(),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Clear menu cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY . '_main');
        Cache::forget(self::CACHE_KEY . '_footer');
    }

    /**
     * Boot — auto clear cache on changes.
     */
    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }
}
