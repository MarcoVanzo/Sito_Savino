<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code', 'type', 'value', 'max_discount', 'min_order_amount',
        'max_uses', 'max_uses_per_user',
        'valid_from', 'valid_until', 'is_active', 'description',
    ];

    protected $casts = [
        'type' => CouponType::class,
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    // --- Relazioni ---

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // --- Scope ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses');
            });
    }

    /**
     * Cerca un coupon per codice (case-insensitive).
     */
    public function scopeByCode($query, string $code)
    {
        return $query->whereRaw('UPPER(code) = ?', [strtoupper($code)]);
    }

    // --- Logica di validazione ---

    /**
     * Verifica se il coupon è valido per un dato ordine.
     */
    public function isValidForOrder(float $subtotal, ?int $userId = null, ?string $guestEmail = null): bool
    {
        // Coupon non attivo
        if (! $this->is_active) {
            return false;
        }

        // Periodo di validità
        if ($this->valid_from && now()->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && now()->gt($this->valid_until)) {
            return false;
        }

        // Limite utilizzi globali
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        // Importo minimo ordine
        if ($this->min_order_amount !== null && $subtotal < (float) $this->min_order_amount) {
            return false;
        }

        // Limite utilizzi per utente
        if ($this->max_uses_per_user !== null && ($userId || $guestEmail)) {
            $usageCount = $this->usages()
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when(! $userId && $guestEmail, fn ($q) => $q->where('guest_email', $guestEmail))
                ->count();

            if ($usageCount >= $this->max_uses_per_user) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcola lo sconto per un dato subtotale.
     */
    public function calculateDiscount(float $subtotal): float
    {
        $discount = match ($this->type) {
            CouponType::Percentage => $subtotal * ($this->value / 100),
            CouponType::Fixed => (float) $this->value,
        };

        // Applica il cap per coupon percentuali
        if ($this->type === CouponType::Percentage && $this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        // Lo sconto non può superare il subtotale
        return min($discount, $subtotal);
    }

    /**
     * Incrementa atomicamente il contatore utilizzi.
     */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
