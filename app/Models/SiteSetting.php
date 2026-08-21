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
     * Il valore di un'impostazione.
     *
     * Si accetta sia la chiave nuda (`press_email`) sia la forma
     * `gruppo.chiave` (`contact.press_email`): il gruppo serve a raccogliere le
     * impostazioni nel pannello e non fa parte della chiave, ma scriverlo è
     * naturale e chi lo faceva otteneva sempre `null` senza capire perché.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::getAllCached();

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        // "contact.press_email" -> cerca la chiave "press_email".
        if (str_contains($key, '.')) {
            [$gruppo, $chiave] = explode('.', $key, 2);
            $raggruppate = static::getAllGrouped();

            if (isset($raggruppate[$gruppo]) && array_key_exists($chiave, $raggruppate[$gruppo])) {
                return $raggruppate[$gruppo][$chiave];
            }
        }

        return $default;
    }

    /**
     * Salva un'impostazione.
     *
     * La chiave si scrive così com'è: chi usa la forma `gruppo.chiave` la
     * conserva intera (è la convenzione di `shop.*` e `lvf.*`, letta con
     * `get()` nello stesso modo). Il gruppo si passa a parte quando serve.
     */
    public static function set(string $key, mixed $value, ?string $group = null): void
    {
        $attributi = ['value' => is_array($value) ? json_encode($value) : $value];

        if ($group !== null) {
            $attributi['group'] = $group;
        }

        static::updateOrCreate(['key' => $key], $attributi);

        static::clearCache();
    }

    /**
     * Il valore di un'impostazione lingua per lingua, così com'è in tabella.
     *
     * `get()` restituisce la traduzione della lingua corrente: va bene per il
     * sito, non per il pannello, che deve poterle modificare tutte. Un valore
     * ancora in testo semplice viene riportato sulla prima lingua.
     *
     * @return array<string, mixed>
     */
    public static function perLocale(string $key): array
    {
        $locales = config('app.supported_locales', ['it', 'en']);
        $raw = static::query()->where('key', $key)->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($decoded) || array_intersect($locales, array_keys($decoded)) === []) {
            // Valore non ancora tradotto: vale per la lingua principale. Può
            // essere un testo o un elenco già decodificato (le impostazioni di
            // tipo `json`, come i numeri della homepage).
            $decoded = [reset($locales) => is_array($decoded) ? $decoded : (string) $raw];
        }

        $out = [];

        foreach ($locales as $locale) {
            $valore = $decoded[$locale] ?? null;
            $out[$locale] = is_array($valore) ? $valore : (string) ($valore ?? '');
        }

        return $out;
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

                $gruppo = $setting->group;
                $chiave = $setting->key;

                // Alcune pagine del pannello nominano i campi `gruppo.chiave`
                // e salvano la chiave intera, senza riempire la colonna del
                // gruppo. Il sito legge le impostazioni raggruppate: senza
                // questa riga i documenti legali caricati dal pannello non
                // arrivavano mai al footer.
                if (str_contains($chiave, '.')) {
                    [$gruppoImplicito, $chiaveNuda] = explode('.', $chiave, 2);

                    // 'general' e' il valore di default della colonna: vuol
                    // dire "nessuno ha scelto", non "gruppo generale".
                    if (in_array($gruppo, [null, '', 'general', $gruppoImplicito], true)) {
                        $gruppo = $gruppoImplicito;
                        $chiave = $chiaveNuda;
                    }
                }

                $grouped[$gruppo][$chiave] = static::resolveForLocale($value);
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
        static::saved(fn () => self::svuotaLeCache());
        static::deleted(fn () => self::svuotaLeCache());
    }

    /**
     * Anche il menu dipende dalle impostazioni.
     *
     * Le voci di Corporate Governance puntano ai PDF caricati in Documenti
     * Legali e l'albero del menu resta in cache un giorno: senza questo,
     * sostituire un documento dal pannello non cambiava nulla online.
     */
    private static function svuotaLeCache(): void
    {
        static::clearCache();
        MenuItem::clearCache();
    }
}
