<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory, MassPrunable;

    protected $fillable = [
        'session_id', 'user_id', 'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Carrelli scaduti da più di 7 giorni vengono eliminati automaticamente.
     * Eseguire: php artisan model:prune
     */
    public function prunable()
    {
        return static::where('expires_at', '<', now()->subDays(7))
            ->orWhere(function ($query) {
                $query->whereNull('expires_at')
                    ->where('updated_at', '<', now()->subDays(7));
            });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
