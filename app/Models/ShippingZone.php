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
        'name', 'countries', 'flat_rate', 'weight_rates', 'free_threshold',
        'estimated_days_min', 'estimated_days_max', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'countries' => 'array',
        'weight_rates' => 'array',
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
     *
     * Protected e non private: è invocata via `static::`, quindi da una
     * sottoclasse il metodo privato non sarebbe accessibile (fatal error).
     */
    protected static function getCachedZones()
    {
        $rawZones = Cache::remember('shipping_zones_active', 3600, function () {
            return static::active()->ordered()->get()->map->getAttributes()->toArray();
        });

        return static::hydrate($rawZones);
    }

    /**
     * Calcola il costo spedizione per un dato subtotale e peso (in kg).
     *
     * La soglia della spedizione gratuita viene prima di tutto: e' una
     * promessa fatta al cliente nel carrello, e vale qualunque sia il collo.
     * Poi si cerca la fascia di peso; senza fasce vale la tariffa base, che
     * e' il comportamento storico.
     */
    public function calculateShippingCost(float $subtotal, float $peso = 0.0): float
    {
        if ($this->free_threshold && $subtotal >= $this->free_threshold) {
            return 0.00;
        }

        $fascia = $this->fasciaPerIlPeso($peso);

        return $fascia !== null ? $fascia : (float) $this->flat_rate;
    }

    /**
     * La tariffa della prima fascia che contiene questo peso.
     *
     * Le fasce sono ordinate per peso crescente e l'ultima puo' non avere un
     * limite: e' quella che prende tutto il resto. Se le fasce ci sono ma
     * nessuna copre il peso — l'ultima e' chiusa e il collo la supera —
     * si torna null e decide la tariffa base, perche' rifiutare la spedizione
     * a carrello pieno sarebbe peggio.
     */
    private function fasciaPerIlPeso(float $peso): ?float
    {
        foreach ($this->fasceOrdinate() as $fascia) {
            $limite = $fascia['max_weight'] ?? null;

            if ($limite === null || $peso <= (float) $limite) {
                return (float) $fascia['rate'];
            }
        }

        return null;
    }

    /**
     * Le fasce valide, dalla piu' leggera alla piu' pesante.
     *
     * L'ordine non si puo' dare per scontato: e' un elenco compilato a mano
     * dal pannello, e una fascia fuori posto farebbe pagare la tariffa
     * sbagliata. Quelle senza tariffa non sono fasce.
     *
     * @return list<array{max_weight: float|null, rate: float}>
     */
    public function fasceOrdinate(): array
    {
        $fasce = [];

        foreach (is_array($this->weight_rates) ? $this->weight_rates : [] as $fascia) {
            if (! is_array($fascia) || ! isset($fascia['rate']) || ! is_numeric($fascia['rate'])) {
                continue;
            }

            $limite = $fascia['max_weight'] ?? null;

            $fasce[] = [
                'max_weight' => is_numeric($limite) ? (float) $limite : null,
                'rate' => (float) $fascia['rate'],
            ];
        }

        usort($fasce, fn (array $a, array $b) => match (true) {
            $a['max_weight'] === null => 1,
            $b['max_weight'] === null => -1,
            default => $a['max_weight'] <=> $b['max_weight'],
        });

        return $fasce;
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
