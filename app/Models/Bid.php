<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `user` può mancare se l'account che ha rilanciato è stato rimosso.
 *
 * @property-read User|null $user
 */
class Bid extends Model
{
    use HasFactory, LogsActivity;

    /**
     * Note: is_valid and invalidated_at are intentionally excluded from $fillable.
     * Use the invalidate() method to change bid validity.
     */
    protected $fillable = [
        'auction_id', 'user_id', 'amount', 'placed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_valid' => 'boolean',
        'invalidated_at' => 'datetime',
        'placed_at' => 'datetime',
    ];

    // --- Relazioni ---

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scope ---

    /**
     * Solo offerte valide (non invalidate).
     */
    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    /**
     * Offerte ordinate per importo decrescente.
     */
    public function scopeHighestFirst($query)
    {
        return $query->orderByDesc('amount');
    }

    /**
     * Invalida questa offerta.
     */
    public function invalidate(): void
    {
        $this->forceFill([
            'is_valid' => false,
            'invalidated_at' => now(),
        ])->save();
    }
}
