<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ShopEvent extends Model
{
    /**
     * Disabilita updated_at — gli eventi sono immutabili.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'event_type', 'viewable_type', 'viewable_id',
        'user_id', 'session_id', 'ip_address', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // --- Relazioni ---

    /**
     * Relazione polimorfica all'entità tracciata.
     */
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scope ---

    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeViews($query)
    {
        return $query->ofType('view');
    }

    public function scopeAddToCart($query)
    {
        return $query->ofType('add_to_cart');
    }

    public function scopeCheckouts($query)
    {
        return $query->ofType('begin_checkout');
    }

    public function scopePurchases($query)
    {
        return $query->ofType('purchase');
    }

    public function scopeInPeriod($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
