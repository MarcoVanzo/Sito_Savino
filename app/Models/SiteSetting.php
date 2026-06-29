<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['key', 'value', 'type', 'group', 'label', 'description', 'sort_order'];

    /**
     * Cache key prefix for site settings.
     */
    private const CACHE_KEY = 'site_settings';

    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Get a setting value by key with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::getAllCached();

        return $settings[$key] ?? $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value]
        );

        static::clearCache();
    }

    /**
     * Get all settings as a flat key => value array, cached.
     */
    public static function getAllCached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return static::pluck('value', 'key')->toArray();
        });
    }

    /**
     * Get all settings grouped by their group, cached.
     */
    public static function getAllGrouped(): array
    {
        return Cache::remember(self::CACHE_KEY.'_grouped', self::CACHE_TTL, function () {
            $settings = static::orderBy('group')->orderBy('sort_order')->get();
            $grouped = [];

            foreach ($settings as $setting) {
                $value = $setting->value;

                // Auto-decode JSON values
                if ($setting->type === 'json' && is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }

                // Cast booleans
                if ($setting->type === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }

                $grouped[$setting->group][$setting->key] = $value;
            }

            return $grouped;
        });
    }

    /**
     * Get settings for a specific group.
     */
    public static function getGroup(string $group): array
    {
        $all = static::getAllGrouped();

        return $all[$group] ?? [];
    }

    /**
     * Clear all settings cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'_grouped');
    }

    /**
     * Boot the model — clear cache on save/delete.
     */
    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }
}
