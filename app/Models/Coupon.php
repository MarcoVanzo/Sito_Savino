<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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

    /**
     * I prodotti su cui il coupon vale, se è limitato a qualcuno.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    /**
     * Le categorie su cui il coupon vale: evitano di riscrivere l'elenco dei
     * prodotti a ogni articolo nuovo dello stesso reparto.
     *
     * @return BelongsToMany<ProductCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'coupon_product_category');
    }

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

    // --- Limiti di catalogo ---

    /**
     * Il coupon vale solo su una parte del catalogo.
     *
     * Senza prodotti né categorie vale su tutto, che è il comportamento
     * storico e quello dei saldi generici.
     */
    public function haLimitiDiCatalogo(): bool
    {
        return $this->prodottiAmmessi()->isNotEmpty() || $this->categorieAmmesse()->isNotEmpty();
    }

    /**
     * Questo articolo rientra fra quelli scontabili.
     *
     * Prodotti e categorie si sommano: basta comparire in uno dei due
     * elenchi. La categoria è quella del prodotto, non la sua discendenza:
     * scegliendo "Kit Gara" non si scontano da sole le sottocategorie, che
     * vanno aggiunte se servono.
     */
    public function valePerIlProdotto(Product $prodotto): bool
    {
        if (! $this->haLimitiDiCatalogo()) {
            return true;
        }

        if ($this->prodottiAmmessi()->contains($prodotto->getKey())) {
            return true;
        }

        return $prodotto->product_category_id !== null
            && $this->categorieAmmesse()->contains($prodotto->product_category_id);
    }

    /**
     * @return Collection<int, int>
     */
    private function prodottiAmmessi(): Collection
    {
        $this->loadMissing('products:id');

        return $this->products->pluck('id');
    }

    /**
     * @return Collection<int, int>
     */
    private function categorieAmmesse(): Collection
    {
        $this->loadMissing('categories:id');

        return $this->categories->pluck('id');
    }

    // --- Logica di validazione ---

    /**
     * Verifica se il coupon è valido per un dato ordine.
     */
    public function isValidForOrder(float $subtotal, ?int $userId = null, ?string $guestEmail = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->periodoValido()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($this->min_order_amount !== null && $subtotal < (float) $this->min_order_amount) {
            return false;
        }

        return ! $this->limitePerUtenteRaggiunto($userId, $guestEmail);
    }

    /**
     * Siamo dentro la finestra di validita' del coupon.
     */
    private function periodoValido(): bool
    {
        if ($this->valid_from && now()->lt($this->valid_from)) {
            return false;
        }

        return ! ($this->valid_until && now()->gt($this->valid_until));
    }

    /**
     * Questo utente (o questa email ospite) ha gia' esaurito i suoi utilizzi.
     *
     * La colonna e' NOT NULL default 1, quindi il vecchio `!== null` era sempre
     * vero e un valore 0 rendeva il coupon inutilizzabile da chiunque.
     * Semantica esplicita: 0 (o null) = nessun limite per utente.
     */
    private function limitePerUtenteRaggiunto(?int $userId, ?string $guestEmail): bool
    {
        $maxPerUser = $this->max_uses_per_user === null ? 0 : (int) $this->max_uses_per_user;

        if ($maxPerUser <= 0 || (! $userId && ! $guestEmail)) {
            return false;
        }

        $usageCount = $this->usages()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(! $userId && $guestEmail, fn ($q) => $q->where('guest_email', $guestEmail))
            ->count();

        return $usageCount >= $maxPerUser;
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
