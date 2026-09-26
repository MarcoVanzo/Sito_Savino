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
        'personalizzazione', 'supplemento_personalizzazione', 'stato_articolo',
    ];

    /**
     * `personalizzazione` e' il nome dell'aggiunta com'era al momento
     * dell'acquisto, per lingua ({"it": "Firma della giocatrice", …}): la
     * redazione puo' rinominarla o toglierla dal prodotto, l'ordine deve
     * continuare a dire cosa e' stato pagato. Nulla = riga senza aggiunta.
     * Il supplemento e' gia' dentro `price_at_time_of_purchase`.
     *
     * `stato_articolo` e' lo stato dichiarato nella scheda di un articolo
     * indossato o autografato, fotografato per lingua allo stesso modo: e' la
     * descrizione a cui rimandano le condizioni di vendita.
     */
    protected $casts = [
        'price_at_time_of_purchase' => 'decimal:2',
        'personalizzazione' => 'array',
        'supplemento_personalizzazione' => 'decimal:2',
        'stato_articolo' => 'array',
    ];

    protected $appends = ['nome_personalizzazione', 'testo_stato_articolo'];

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
        return self::testoInLingua($this->personalizzazione, $locale);
    }

    /**
     * Lo stato dell'articolo al momento dell'acquisto, nella lingua corrente,
     * per il frontend (pagina dell'ordine).
     *
     * @return Attribute<string|null, never>
     */
    protected function testoStatoArticolo(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->statoArticoloIn());
    }

    /**
     * Lo stato dell'articolo al momento dell'acquisto nella lingua richiesta,
     * con ripiego sull'italiano.
     */
    public function statoArticoloIn(?string $locale = null): ?string
    {
        return self::testoInLingua($this->stato_articolo, $locale);
    }

    /**
     * @param  mixed  $perLingua  {"it": "…", "en": "…"} o null
     */
    private static function testoInLingua(mixed $perLingua, ?string $locale): ?string
    {
        if (! is_array($perLingua)) {
            return null;
        }

        $locale ??= app()->getLocale();
        $testo = $perLingua[$locale] ?? null;

        if (! is_string($testo) || trim($testo) === '') {
            $testo = $perLingua[config('app.fallback_locale')] ?? null;
        }

        return is_string($testo) && trim($testo) !== '' ? $testo : null;
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
