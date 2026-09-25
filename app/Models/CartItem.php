<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'cart_id', 'product_id', 'product_variant_id', 'con_personalizzazione', 'quantity',
    ];

    protected $casts = [
        'con_personalizzazione' => 'boolean',
    ];

    protected $appends = ['unit_price', 'personalizzazione'];

    public function getUnitPriceAttribute(): float
    {
        return $this->prezzoUnitario();
    }

    /**
     * Il nome della personalizzazione scelta, nella lingua corrente; null se
     * la riga non e' personalizzata. E' cio' che carrello e checkout mostrano
     * sotto la taglia.
     */
    public function getPersonalizzazioneAttribute(): ?string
    {
        return $this->personalizzata() ? $this->product->personalizzazione_nome : null;
    }

    /**
     * Il prezzo di un pezzo: prezzo effettivo del prodotto, variazione della
     * taglia e supplemento della personalizzazione.
     *
     * E' l'unico posto in cui si compone: carrello, cassetto, checkout e
     * ordine lo chiedono qui. Prima la stessa somma era scritta in cinque punti.
     */
    public function prezzoUnitario(): float
    {
        if (! $this->product) {
            return 0.0;
        }

        $modifier = $this->variant ? (float) $this->variant->price_modifier : 0.0;

        return $this->product->effectivePrice() + $modifier + $this->supplementoPersonalizzazione();
    }

    /**
     * Il supplemento, se la riga e' personalizzata e il prodotto offre ancora
     * la personalizzazione: tolta dal pannello mentre la riga era nel
     * carrello, la riga torna semplice invece di pagare un'aggiunta che nessuno
     * fara'.
     */
    public function supplementoPersonalizzazione(): float
    {
        if (! $this->personalizzata()) {
            return 0.0;
        }

        return (float) $this->product->personalizzazione_prezzo;
    }

    public function personalizzata(): bool
    {
        return $this->con_personalizzazione
            && $this->product !== null
            && $this->product->offrePersonalizzazione();
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
