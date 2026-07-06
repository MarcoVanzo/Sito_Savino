<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Translatable\HasTranslations;

class ShippingZone extends Model
{
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * Note: 'name' is a JSON (translatable) column. MySQL 8.4 does not support
     * unique indexes on JSON columns, so uniqueness is enforced at the
     * application level via Filament validation (unique rule in ShippingZoneResource).
     */
    public $translatable = ['name'];

    protected $fillable = [
        'name', 'countries', 'flat_rate', 'free_threshold',
        'estimated_days_min', 'estimated_days_max', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'countries' => 'array',
        'flat_rate' => 'decimal:2',
        'free_threshold' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Scope: solo zone attive.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: ordinate per sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('shipping_zones_active'));
        static::deleted(fn () => Cache::forget('shipping_zones_active'));
    }

    /**
     * Trova la zona di spedizione per un dato codice paese ISO.
     * Cerca prima una corrispondenza esatta, poi la zona wildcard '*'.
     * Risultati cachati per 1 ora.
     */
    public static function findByCountry(string $countryCode): ?self
    {
        $zones = static::getCachedZones();
        $code = strtoupper($countryCode);

        // Prima: corrispondenza esatta del codice paese
        $exact = $zones->first(fn (self $zone) => in_array($code, $zone->countries));
        if ($exact) {
            return $exact;
        }

        // Fallback: zona wildcard (catch-all per Extra-EU)
        return $zones->first(fn (self $zone) => in_array('*', $zone->countries));
    }

    /**
     * Zone attive cachate per 1 ora. Salva come array di attributi
     * per evitare problemi di serializzazione con il file cache driver.
     */
    private static function getCachedZones()
    {
        $rawZones = Cache::remember('shipping_zones_active', 3600, function () {
            return static::active()->ordered()->get()->map->getAttributes()->toArray();
        });

        return static::hydrate($rawZones);
    }

    /**
     * Calcola il costo spedizione per un dato subtotale.
     */
    public function calculateShippingCost(float $subtotal): float
    {
        if ($this->free_threshold && $subtotal >= $this->free_threshold) {
            return 0.00;
        }

        return (float) $this->flat_rate;
    }

    /**
     * Override toArray per risolvere i campi translatable
     * alla locale corrente quando serializzato per Inertia/JSON.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        foreach ($this->translatable as $field) {
            $array[$field] = $this->getTranslation($field, app()->getLocale());
        }

        return $array;
    }
}
