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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Note: payment_id, status, paid_at and auction_id are intentionally
     * excluded from $fillable to prevent mass-assignment of sensitive fields.
     * Set status and auction_id via service code, paid_at via webhook handlers.
     */
    protected $fillable = [
        'user_id', 'total_price',
        'shipping_address', 'billing_address',
        'order_number', 'order_token', 'guest_email', 'guest_name',
        'guest_phone', 'phone', 'country', 'billing_country', 'codice_fiscale', 'payment_gateway',
        'shipped_at', 'tracking_number', 'tracking_url',
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
                    'order_number' => 'ORD-'.now()->format('Y').'-'.str_pad($order->id, 5, '0', STR_PAD_LEFT),
                    'order_token' => $order->order_token ?? Str::uuid()->toString(),
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Asta di provenienza, per gli ordini nati dalla vincita di un'asta.
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
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
     * Stati in cui il totale non va più toccato: l'importo è già stato incassato
     * e il ricalcolo lo disallineerebbe dal pagamento registrato.
     */
    private const LOCKED_TOTAL_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
        OrderStatus::Refunded,
    ];

    /**
     * Ricalcola il totale ordine dalla somma degli items,
     * includendo costo spedizione e sconto coupon.
     *
     * Non fa nulla su un ordine già pagato.
     */
    public function recalculateTotal(): self
    {
        return DB::transaction(function () {
            // Acquisisce lock FOR UPDATE correttamente
            $locked = static::lockForUpdate()->find($this->id);

            // L'ordine può essere stato eliminato nel frattempo (o non esistere
            // ancora a DB): senza questa guardia si andava in fatal su ->items().
            if (! $locked) {
                return $this;
            }

            if ($locked->paid_at !== null || in_array($locked->status, self::LOCKED_TOTAL_STATUSES, true)) {
                Log::warning(
                    "Ricalcolo totale ignorato per l'ordine #{$locked->id}: già pagato/incassato."
                );

                $this->setRawAttributes($locked->getAttributes());

                return $this;
            }

            $itemsTotal = (float) $locked->items()
                ->sum(DB::raw('quantity * price_at_time_of_purchase'));

            // Stesso arrotondamento applicato da CheckoutService alla creazione
            $locked->total_price = round(
                max(0, $itemsTotal + (float) $locked->shipping_cost - (float) $locked->coupon_discount),
                2
            );
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
        $addr = $this->normalizeAddress($this->shipping_address, 'shipping_address');

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
        $addr = $this->normalizeAddress($this->billing_address, 'billing_address');

        if ($this->billing_country) {
            $addr['country'] = $this->billing_country;
        }

        return $addr;
    }

    /**
     * Normalizza un indirizzo in array, recuperando anche le righe legacy
     * salvate come testo semplice.
     *
     * Il ramo `is_string($addr)` sull'attributo castato non scattava mai: con il
     * cast `array` un valore legacy non-JSON viene decodificato a null, quindi la
     * retrocompatibilità restituiva un indirizzo vuoto. Il testo grezzo va letto
     * dal valore originale della colonna.
     */
    private function normalizeAddress(mixed $addr, string $column): array
    {
        if (is_array($addr)) {
            return $addr;
        }

        $raw = $this->getRawOriginal($column);

        if (is_string($raw) && trim($raw) !== '') {
            return ['raw_address' => $raw];
        }

        return [];
    }
}
