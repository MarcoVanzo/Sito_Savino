<?php

namespace App\Models;

use App\Enums\AuctionStatus;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Auction extends Model
{
    use HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    /**
     * Note: status, winner_user_id, winner_checkout_token, winner_checkout_deadline,
     * current_winner_attempt are intentionally excluded from $fillable to prevent
     * auction manipulation via mass assignment. Set them only via service code.
     */
    protected $fillable = [
        'product_id', 'title', 'description', 'size',
        'starting_price', 'current_bid', 'reserve_price',
        'bid_increment', 'max_bid_jump',
        'start_date', 'end_date',
        'is_charity', 'charity_description',
    ];

    public $translatable = ['title', 'description', 'charity_description'];

    protected $casts = [
        'starting_price' => 'decimal:2',
        'current_bid' => 'decimal:2',
        'reserve_price' => 'decimal:2',
        'bid_increment' => 'decimal:2',
        'max_bid_jump' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'status' => AuctionStatus::class,
        'winner_checkout_deadline' => 'datetime',
        'is_charity' => 'boolean',
    ];

    // --- Relazioni ---

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<Bid, $this>
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    /**
     * Ordini generati dall'asta. Ce n'è al massimo uno per utente: se il
     * vincitore non paga entro la deadline l'asta passa all'offerente
     * successivo, che apre il proprio ordine.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Offerte valide ordinate per importo decrescente.
     *
     * @return HasMany<Bid, $this>
     */
    public function validBids(): HasMany
    {
        return $this->bids()->valid()->highestFirst();
    }

    // --- Scope ---

    public function scopeActive($query)
    {
        return $query->where('status', AuctionStatus::Active);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', AuctionStatus::Scheduled);
    }

    public function scopeEnded($query)
    {
        return $query->where('status', AuctionStatus::Ended);
    }

    /**
     * Aste che devono essere attivate (programmate con start_date passata).
     */
    public function scopeReadyToActivate($query)
    {
        return $query->scheduled()->where('start_date', '<=', now());
    }

    /**
     * Aste che devono essere chiuse (attive con end_date passata).
     */
    public function scopeReadyToClose($query)
    {
        return $query->active()->where('end_date', '<=', now());
    }

    // --- Helper ---

    /**
     * Verifica se l'asta è attualmente attiva.
     */
    public function isActive(): bool
    {
        return $this->status === AuctionStatus::Active;
    }

    /**
     * Verifica se il prezzo di riserva è stato raggiunto.
     * Se non è impostato un reserve_price, il risultato è sempre true.
     */
    public function isReserveMet(): bool
    {
        if (! $this->reserve_price) {
            return true;
        }

        $currentAmount = $this->current_bid ?? 0;

        return (float) $currentAmount >= (float) $this->reserve_price;
    }

    /**
     * Prezzo minimo per la prossima offerta.
     */
    public function minimumBidAmount(): float
    {
        $base = $this->current_bid ?? $this->starting_price;

        return round((float) $base + (float) $this->bid_increment, 2);
    }

    /**
     * Prezzo massimo consentito per una offerta.
     */
    public function maximumBidAmount(): float
    {
        $base = $this->current_bid ?? $this->starting_price;

        return round((float) $base + (float) $this->max_bid_jump, 2);
    }

    /**
     * Offerta più alta valida.
     */
    public function highestBid(): ?Bid
    {
        return $this->validBids()->first();
    }

    /**
     * Verifica se siamo nel periodo di anti-sniping.
     */
    public function isInAntiSnipePeriod(int $minutes = 5): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $remaining = now()->diffInMinutes($this->end_date, false);

        return $remaining >= 0 && $remaining <= $minutes;
    }

    /**
     * Estendi l'asta di N minuti (anti-sniping).
     */
    public function extendByMinutes(int $minutes): void
    {
        $this->update([
            'end_date' => $this->end_date->addMinutes($minutes),
        ]);
    }
}
