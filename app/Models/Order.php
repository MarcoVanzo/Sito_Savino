<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Note: payment_id is intentionally excluded from $fillable
     * to prevent mass-assignment of this sensitive field.
     */
    protected $fillable = [
        'user_id', 'status', 'total_price',
        'shipping_address', 'billing_address',
        'order_number', 'order_token', 'guest_email', 'guest_name',
        'guest_phone', 'phone', 'country', 'billing_country', 'codice_fiscale', 'payment_gateway',
        'paid_at', 'shipped_at', 'tracking_number', 'tracking_url',
        'shipping_cost', 'coupon_id', 'coupon_discount', 'notes',
        'privacy_accepted_at',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'status' => OrderStatus::class,
        'payment_gateway' => PaymentGateway::class,
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'shipping_cost' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'privacy_accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if (! $order->order_number) {
                $order->updateQuietly([
                    'order_number' => 'ORD-' . now()->format('Y') . '-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
                    'order_token' => $order->order_token ?? Str::uuid()->toString(),
                ]);
            }
        });
    }

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

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponUsage(): HasOne
    {
        return $this->hasOne(CouponUsage::class);
    }

    public function scopePaid($query)
    {
        return $query->where('status', OrderStatus::Paid);
    }

    public function scopeForGuest($query, string $email)
    {
        return $query->where('guest_email', $email);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Ricalcola il totale ordine dalla somma degli items,
     * includendo costo spedizione e sconto coupon.
     */
    public function recalculateTotal(): self
    {
        return DB::transaction(function () {
            // Acquisisce lock FOR UPDATE correttamente
            $locked = static::lockForUpdate()->find($this->id);

            $itemsTotal = $locked->items()
                ->sum(DB::raw('quantity * price_at_time_of_purchase'));

            $locked->total_price = max(0, $itemsTotal + (float) $locked->shipping_cost - (float) $locked->coupon_discount);
            $locked->save();

            // Sincronizza l'istanza corrente con i valori aggiornati
            $this->setRawAttributes($locked->getAttributes());

            return $this;
        });
    }

    /**
     * Accessor: returns a normalized shipping address array,
     * handling both old (raw text / raw_address) and new (structured) formats.
     */
    public function getFormattedShippingAddressAttribute(): array
    {
        $addr = $this->shipping_address;
        if (is_string($addr)) {
            $addr = ['raw_address' => $addr];
        } elseif (is_array($addr) && isset($addr['raw_address'])) {
            // keep as-is
        } else {
            $addr = $addr ?? [];
        }

        if ($this->country) {
            $addr['country'] = $this->country;
        }

        return $addr;
    }

    /**
     * Accessor: returns a normalized billing address array,
     * handling both old (raw text / raw_address) and new (structured) formats.
     */
    public function getFormattedBillingAddressAttribute(): array
    {
        $addr = $this->billing_address;
        if (is_string($addr)) {
            $addr = ['raw_address' => $addr];
        } elseif (is_array($addr) && isset($addr['raw_address'])) {
            // keep as-is
        } else {
            $addr = $addr ?? [];
        }

        if ($this->billing_country) {
            $addr['country'] = $this->billing_country;
        }

        return $addr;
    }
}
