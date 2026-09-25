<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `product` è null quando il prodotto è stato archiviato: Product usa
 * SoftDeletes, e la scope predefinita lo esclude dalla relazione anche se
 * `order_items.product_id` è NOT NULL con `on_delete=restrict` e la riga nel
 * database c'è ancora. È il modo normale in cui un prodotto esce di catalogo,
 * quindi capita su ogni ordine vecchio.
 *
 * `variant` è opzionale: i prodotti senza varianti non ne hanno.
 *
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 */
class OrderItem extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'quantity', 'price_at_time_of_purchase',
        'personalizzazione', 'supplemento_personalizzazione',
    ];

    /**
     * `personalizzazione` e' il nome dell'aggiunta com'era al momento
     * dell'acquisto, per lingua ({"it": "Firma della giocatrice", …}): la
     * redazione puo' rinominarla o toglierla dal prodotto, l'ordine deve
     * continuare a dire cosa e' stato pagato. Nulla = riga senza aggiunta.
     * Il supplemento e' gia' dentro `price_at_time_of_purchase`.
     */
    protected $casts = [
        'price_at_time_of_purchase' => 'decimal:2',
        'personalizzazione' => 'array',
        'supplemento_personalizzazione' => 'decimal:2',
    ];

    protected $appends = ['nome_personalizzazione'];

    /**
     * Il nome della personalizzazione nella lingua corrente, per il
     * frontend (pagina dell'ordine).
     *
     * @return Attribute<string|null, never>
     */
    protected function nomePersonalizzazione(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->personalizzazioneIn());
    }

    /**
     * Il nome della personalizzazione nella lingua richiesta, con ripiego
     * sull'italiano.
     */
    public function personalizzazioneIn(?string $locale = null): ?string
    {
        if (! is_array($this->personalizzazione)) {
            return null;
        }

        $locale ??= app()->getLocale();
        $nome = $this->personalizzazione[$locale] ?? null;

        if (! is_string($nome) || trim($nome) === '') {
            $nome = $this->personalizzazione[config('app.fallback_locale')] ?? null;
        }

        return is_string($nome) && trim($nome) !== '' ? $nome : null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
