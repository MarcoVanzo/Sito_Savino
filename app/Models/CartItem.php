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
        'cart_id', 'product_id', 'product_variant_id', 'quantity',
    ];

    protected $appends = ['unit_price'];

    public function getUnitPriceAttribute(): float
    {
        if (! $this->product) {
            return 0.0;
        }

        $basePrice = $this->product->effectivePrice();
        $modifier = $this->variant ? (float) $this->variant->price_modifier : 0.0;

        return $basePrice + $modifier;
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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
