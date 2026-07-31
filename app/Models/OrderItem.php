<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
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
    ];

    protected $casts = [
        'price_at_time_of_purchase' => 'decimal:2',
    ];

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
