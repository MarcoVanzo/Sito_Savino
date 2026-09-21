<?php

namespace App\Models;

use App\Enums\AuctionStatus;
use App\Enums\ProductType;
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

    /**
     * Transizioni di stato ammesse alla redazione.
     *
     * `status` resta fuori da $fillable perche' nessun modulo deve poterlo
     * scrivere in massa, ed e' questo che rendeva muto il campo "Stato" del
     * pannello: il valore scelto veniva scartato da `fill()` senza un errore,
     * l'asta restava in bozza e la pagina pubblica — che elenca solo le aste
     * attive, programmate e concluse — non la mostrava mai. `cambiaStato()`
     * e' l'unico varco, e verifica la transizione invece di fidarsi delle
     * opzioni disegnate nel form.
     *
     * @var array<string, list<string>>
     */
    public const TRANSIZIONI_AMMESSE = [
        'draft' => ['draft', 'scheduled', 'active', 'cancelled'],
        'scheduled' => ['scheduled', 'active', 'cancelled'],
        'active' => ['active', 'ended', 'cancelled'],
        'ended' => ['ended'],
        'cancelled' => ['cancelled', 'draft'],
    ];

    /**
     * Gli stati raggiungibili da quello indicato, con la loro etichetta.
     *
     * @return array<string, string>
     */
    public static function statiRaggiungibiliDa(?AuctionStatus $stato): array
    {
        $stati = self::TRANSIZIONI_AMMESSE[$stato?->value] ?? array_map(
            fn (AuctionStatus $caso) => $caso->value,
            AuctionStatus::cases(),
        );

        $etichette = [];

        foreach ($stati as $valore) {
            $etichette[$valore] = AuctionStatus::from($valore)->getLabel();
        }

        return $etichette;
    }

    /**
     * Porta l'asta nello stato richiesto, se la transizione e' ammessa.
     *
     * Restituisce false quando il cambio non si puo' fare: chi chiama lo dice
     * a chi l'ha chiesto, perche' un rifiuto silenzioso e' il difetto da cui
     * nasce questo metodo.
     */
    public function cambiaStato(AuctionStatus|string|null $nuovo): bool
    {
        $nuovo = $nuovo instanceof AuctionStatus ? $nuovo : AuctionStatus::tryFrom((string) $nuovo);

        if ($nuovo === null || $nuovo === $this->status) {
            return false;
        }

        if (! array_key_exists($nuovo->value, self::statiRaggiungibiliDa($this->status))) {
            return false;
        }

        $this->forceFill(['status' => $nuovo])->save();

        return true;
    }

    /**
     * Il prodotto di un'asta esce dallo shop, e ci rientra quando l'asta non
     * c'e' piu'.
     *
     * Il tipo `auction` tiene il pezzo fuori dalla griglia e dal carrello
     * (`Product::scopeShoppable`). Finche' il cambio si faceva solo alla
     * creazione dell'asta, cancellarla lasciava il prodotto invisibile per
     * sempre: la sua pagina rispondeva 404 e in redazione non c'era modo di
     * capire perche'. Al ritorno il tipo si deduce dalle varianti, perche'
     * quello di partenza non e' conservato da nessuna parte.
     */
    protected static function booted(): void
    {
        static::created(fn (self $asta) => $asta->product?->update(['type' => ProductType::Auction]));

        static::deleted(fn (self $asta) => $asta->riportaIlProdottoNelloShop());

        static::restored(fn (self $asta) => $asta->product?->update(['type' => ProductType::Auction]));
    }

    private function riportaIlProdottoNelloShop(): void
    {
        $prodotto = $this->product;

        if (! $prodotto || $prodotto->type !== ProductType::Auction) {
            return;
        }

        $prodotto->update([
            'type' => $prodotto->variants()->exists() ? ProductType::Variable : ProductType::Simple,
        ]);
    }

    // --- Relazioni ---

    /**
     * @return BelongsTo<Product, $this>
     */
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
