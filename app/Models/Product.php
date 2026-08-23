<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Traits\HasOptimizedMedia;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Product extends Model implements HasMedia
{
    use HasFactory, HasOptimizedMedia, HasTranslations, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'product_category_id', 'name', 'slug', 'description', 'price',
        'stock', 'sku', 'is_active', 'type', 'sale_price', 'sale_start',
        'sale_end', 'short_description', 'weight',
    ];

    public $translatable = ['name', 'description', 'short_description'];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'type' => ProductType::class,
        'sale_price' => 'decimal:2',
        'sale_start' => 'datetime',
        'sale_end' => 'datetime',
        'weight' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Giacenza realmente disponibile.
     *
     * Per i prodotti con varianti la colonna `stock` del prodotto resta a zero:
     * le quantità stanno sulle taglie. Leggere solo `stock` faceva apparire
     * "esaurito" in vetrina un prodotto pieno di magazzino.
     *
     * Usa `withSum('variants', 'stock')` quando la query lo ha già caricato,
     * per non innescare una query per ogni card della griglia.
     */
    public function availableStock(): int
    {
        if ($this->type !== ProductType::Variable) {
            return (int) $this->stock;
        }

        $aggregated = $this->getAttribute('variants_sum_stock');

        if ($aggregated !== null) {
            return (int) $aggregated;
        }

        if ($this->relationLoaded('variants')) {
            return (int) $this->variants->sum('stock');
        }

        return (int) $this->variants()->sum('stock');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function auction(): HasOne
    {
        return $this->hasOne(Auction::class);
    }

    public function scopeShoppable($query)
    {
        return $query->where('is_active', true)
            ->where('type', '!=', ProductType::Auction);
    }

    public function scopeOfType($query, ProductType $type)
    {
        return $query->where('type', $type);
    }

    public function isOnSale(): bool
    {
        if (! $this->sale_price || $this->sale_price <= 0) {
            return false;
        }

        $now = now();

        if ($this->sale_start && $now->lt($this->sale_start)) {
            return false;
        }

        if ($this->sale_end && $now->gt($this->sale_end)) {
            return false;
        }

        return true;
    }

    public function effectivePrice(): float
    {
        return $this->isOnSale() ? (float) $this->sale_price : (float) $this->price;
    }

    /**
     * Restituisce l'URL dell'immagine principale del prodotto per la conversione richiesta.
     * Cerca prima nella collection 'products' (Filament), poi 'images' (WooCommerce).
     */
    public function getImageUrl(string $conversion = 'card'): ?string
    {
        $media = $this->getMedia('products');
        if ($media->isEmpty()) {
            $media = $this->getMedia('images');
        }

        $firstMedia = $media->first();
        if (! $firstMedia) {
            return null;
        }

        return $firstMedia->hasGeneratedConversion($conversion)
            ? $firstMedia->getUrl($conversion)
            : $firstMedia->getUrl();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerStandardConversions();

        $this->addMediaConversion('zoom')
            ->width(1200)
            ->quality(85)
            ->performOnCollections('images', 'products')
            ->nonQueued();

        $this->addMediaConversion('og-image')
            ->width(1200)
            ->height(630)
            ->quality(80)
            ->performOnCollections('images', 'products')
            ->nonQueued();
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
