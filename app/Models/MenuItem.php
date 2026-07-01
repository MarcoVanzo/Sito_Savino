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
        'stagione' => 'stagione.jpg',
        'società' => 'societa.jpg',
        'ticketing' => 'ticketing.jpg',
        'sponsor' => 'sponsor.jpg',
        'sdb youth' => 'youth.jpg',
        'summer camp' => 'camp.jpg',
        'sociale' => 'sociale.jpg',
        'comunicazione' => 'media.jpg',
        'shop ufficiale' => 'shop.jpg',
    ];

    /**
     * Get the full navigation tree for a location, cached.
     */
    public static function getTree(string $location = 'main'): array
    {
        $locale = app()->getLocale();

        return Cache::remember(self::CACHE_KEY.'_'.$location.'_'.$locale, self::CACHE_TTL, function () use ($location, $locale) {
            $prefix = $locale === 'it' ? '' : '/'.$locale;

            return static::where('location', $location)
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->with(['children'])
                ->orderBy('sort_order')
                ->get()
                ->map(function ($item) use ($prefix) {
                    $menuImage = $item->getFirstMediaUrl('menu-images') ?: null;
                    $normalizedLabel = mb_strtolower(trim($item->label));
                    if (! $menuImage && isset(self::$staticMenuImages[$normalizedLabel])) {
                        $menuImage = '/images/menu/'.self::$staticMenuImages[$normalizedLabel];
                    }

                    $url = rtrim($item->url ?: '/in-costruzione', '/');
                    $url = $url === '' ? '/' : $url;
                    $href = str_starts_with($url, '/') ? $prefix.$url : $url;

                    return [
                        'id' => $item->id,
                        'label' => $item->label,
                        'href' => $href,
                        'description' => $item->description,
                        'mottoTitle' => $item->motto_title,
                        'mottoSubtitle' => $item->motto_subtitle,
                        'menuImage' => $menuImage,
                        'isHighlight' => $item->is_highlight,
                        'children' => $item->children->map(function ($child) use ($prefix) {
                            $childUrl = rtrim($child->url ?: '/in-costruzione', '/');
                            $childUrl = $childUrl === '' ? '/' : $childUrl;

                            return [
                                'id' => $child->id,
                                'label' => $child->label,
                                'href' => str_starts_with($childUrl, '/') ? $prefix.$childUrl : $childUrl,
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
        foreach (['it', 'en'] as $locale) {
            Cache::forget(self::CACHE_KEY.'_main_'.$locale);
            Cache::forget(self::CACHE_KEY.'_footer_'.$locale);
        }
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
