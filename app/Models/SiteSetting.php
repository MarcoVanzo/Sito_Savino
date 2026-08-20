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
        return Cache::remember(self::CACHE_KEY.'_'.app()->getLocale(), self::CACHE_TTL, function () {
            return static::pluck('value', 'key')
                ->map(fn ($value) => static::resolveForLocale($value))
                ->toArray();
        });
    }

    /**
     * Get all settings grouped by their group, cached.
     */
    public static function getAllGrouped(): array
    {
        return Cache::remember(self::CACHE_KEY.'_grouped_'.app()->getLocale(), self::CACHE_TTL, function () {
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

                $grouped[$setting->group][$setting->key] = static::resolveForLocale($value);
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
     * Get only public-safe settings groups for frontend exposure.
     * Prevents internal configuration from leaking to the client.
     */
    public static function getPublicGrouped(): array
    {
        $all = static::getAllGrouped();

        // `analytics` contiene solo il Measurement ID, che il tag di Google
        // espone comunque in chiaro nel browser: non è un segreto e deve
        // arrivare al front-end per poter caricare la misurazione.
        $publicGroups = ['general', 'brand', 'footer', 'shop', 'social', 'auctions', 'hero', 'legal', 'contact', 'home', 'analytics'];

        return array_intersect_key($all, array_flip($publicGroups));
    }

    /**
     * Clear all settings cache.
     */
    public static function clearCache(): void
    {
        foreach (config('app.supported_locales', ['it', 'en']) as $locale) {
            Cache::forget(self::CACHE_KEY.'_'.$locale);
            Cache::forget(self::CACHE_KEY.'_grouped_'.$locale);
        }
    }

    /**
     * Protected e non private: è invocata via `static::`, quindi da una
     * sottoclasse il metodo privato non sarebbe accessibile (fatal error).
     */
    protected static function resolveForLocale(mixed $value): mixed
    {
        // Le impostazioni di tipo `json` (per esempio i numeri della homepage)
        // arrivano qui già decodificate: senza questo ramo il frontend riceveva
        // l'oggetto `{"it": […], "en": […]}` invece dell'elenco della lingua in
        // uso, e la sezione restava vuota.
        if (is_array($value)) {
            return static::pickLocale($value) ?? $value;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return static::pickLocale($decoded) ?? $value;
        }

        return $value;
    }

    /**
     * Restituisce la traduzione per la lingua corrente, oppure null se il
     * valore non è un contenitore per lingua.
     *
     * Protected e non private: è invocata via `static::`, quindi da una
     * sottoclasse il metodo privato non sarebbe accessibile (fatal error).
     */
    protected static function pickLocale(array $value): mixed
    {
        $locales = config('app.supported_locales', ['it', 'en']);
        $presenti = array_intersect($locales, array_keys($value));

        if ($presenti === []) {
            return null;
        }

        return $value[app()->getLocale()] ?? $value[reset($locales)] ?? null;
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
