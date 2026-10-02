<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory, LogsActivity;

    /**
     * Le rettifiche dello staff si rivedono nel registro; gli scarichi di
     * checkout, webhook e annulli automatici no: stanno già in questa tabella
     * e nel registro porterebbero solo il cliente che ha comprato.
     */
    protected bool $logSoloDalPannello = true;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'order_id',
        'quantity',
        'type',
        'notes',
    ];

    protected $casts = [
        'type' => StockMovementType::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
