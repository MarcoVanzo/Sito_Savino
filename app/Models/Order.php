<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'status', 'total_price',
        'shipping_address', 'billing_address',
        'stripe_payment_id',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'status' => OrderStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopePaid($query)
    {
        return $query->where('status', OrderStatus::Paid);
    }

    /**
     * Ricalcola il totale ordine dalla somma degli items.
     * Persiste il valore calcolato nel campo total_price.
     */
    public function recalculateTotal(): self
    {
        return DB::transaction(function () {
            // Lock this order row to prevent concurrent recalculations
            $this->refresh()->lockForUpdate();

            $this->total_price = $this->items()
                ->sum(DB::raw('quantity * price_at_time_of_purchase'));
            $this->save();

            return $this;
        });
    }
}
