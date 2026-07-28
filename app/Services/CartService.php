<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    private ?Cart $resolvedCart = null;

    private bool $cartResolved = false;

    /**
     * Ottiene o crea un carrello per l'utente/sessione corrente.
     */
    public function getOrCreateCart(): Cart
    {
        $cart = $this->findCurrentCart();

        if ($cart) {
            return $cart;
        }

        $expiryDays = (int) SiteSetting::get('shop.cart_expiry_days', 7);

        $attributes = [
            'session_id' => session()->getId(),
            'expires_at' => now()->addDays($expiryDays),
        ];

        if (auth()->check()) {
            $attributes['user_id'] = auth()->id();
        }

        $cart = Cart::create($attributes);

        // Aggiorna la cache in-process: altrimenti findCurrentCart() continuerebbe
        // a restituire il null memorizzato poco sopra per il resto della richiesta.
        $this->resolvedCart = $cart;
        $this->cartResolved = true;

        return $cart;
    }

    /**
     * Aggiunge un prodotto al carrello.
     * Se l'item esiste già (stesso prodotto+variante), aggiorna la quantità.
     *
     * Restituisce l'item aggiornato, o null se nel frattempo è sparito
     * (la firma dichiarava CartItem ma fresh() può restituire null).
     *
     * @throws \InvalidArgumentException
     * @throws \OverflowException
     */
    public function addItem(int $productId, int $quantity = 1, ?int $variantId = null): ?CartItem
    {
        return DB::transaction(function () use ($productId, $quantity, $variantId) {
            $product = Product::lockForUpdate()->findOrFail($productId);

            // Validazione: prodotto attivo e non di tipo Auction
            if (! $product->is_active) {
                throw new \InvalidArgumentException(__('messages.cart.product_unavailable'));
            }

            if ($product->type === ProductType::Auction) {
                throw new \InvalidArgumentException(__('messages.cart.auction_not_allowed'));
            }

            $variant = null;
            if ($variantId) {
                $variant = ProductVariant::lockForUpdate()->findOrFail($variantId);
                if ($variant->product_id !== $product->id) {
                    throw new \InvalidArgumentException(__('messages.cart.invalid_variant'));
                }
            }

            $cart = $this->getOrCreateCart();
            $maxQty = (int) SiteSetting::get('shop.max_qty_per_product', 10);
            $availableStock = $this->getAvailableStock($product, $variant);

            // Cerca item esistente con stesso prodotto+variante
            $existingItem = $cart->items()
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->first();

            $currentQty = $existingItem ? $existingItem->quantity : 0;
            $newQty = $currentQty + $quantity;

            // Validazione quantità
            if ($newQty > $maxQty) {
                throw new \OverflowException(
                    __('messages.cart.max_qty', ['qty' => $maxQty])
                );
            }

            if ($newQty > $availableStock) {
                throw new \OverflowException(
                    __('messages.cart.out_of_stock', ['stock' => $availableStock])
                );
            }

            if ($existingItem) {
                $existingItem->update(['quantity' => $newQty]);
                $this->invalidateCache();

                return $existingItem->fresh();
            }

            $cartItem = $cart->items()->create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
            ]);

            $this->invalidateCache();

            return $cartItem;
        });
    }

    /**
     * Aggiorna la quantità di un item nel carrello.
     * Se la quantità è 0, rimuove l'item e restituisce null.
     *
     * @throws \OverflowException
     */
    public function updateItemQuantity(int $cartItemId, int $quantity): ?CartItem
    {
        return DB::transaction(function () use ($cartItemId, $quantity) {
            $cart = $this->findCurrentCart();
            if (! $cart) {
                throw new \RuntimeException(__('messages.cart.not_found'));
            }

            $item = $cart->items()->with(['product', 'variant'])->findOrFail($cartItemId);

            // Se quantità 0, rimuovi l'item: non c'è più nessun item da restituire
            if ($quantity <= 0) {
                $item->delete();
                $this->invalidateCache();

                return null;
            }

            $product = Product::lockForUpdate()->findOrFail($item->product_id);
            $variant = $item->product_variant_id
                ? ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id)
                : null;

            $maxQty = (int) SiteSetting::get('shop.max_qty_per_product', 10);
            $availableStock = $this->getAvailableStock($product, $variant);

            if ($quantity > $maxQty) {
                throw new \OverflowException(
                    __('messages.cart.max_qty', ['qty' => $maxQty])
                );
            }

            if ($quantity > $availableStock) {
                throw new \OverflowException(
                    __('messages.cart.out_of_stock', ['stock' => $availableStock])
                );
            }

            $item->update(['quantity' => $quantity]);

            $this->invalidateCache();

            return $item->fresh();
        });
    }

    /**
     * Rimuove un item dal carrello.
     */
    public function removeItem(int $cartItemId): void
    {
        $cart = $this->findCurrentCart();
        if (! $cart) {
            return;
        }

        $cart->items()->where('id', $cartItemId)->delete();
        $this->invalidateCache();
    }

    /**
     * Restituisce il carrello corrente con gli items caricati.
     */
    public function getCart(): ?Cart
    {
        $cart = $this->findCurrentCart();

        if ($cart) {
            $this->removeInactiveItems($cart);
            $cart->load(['items.product.media', 'items.variant']);
        }

        return $cart;
    }

    /**
     * Calcola il totale del carrello usando i prezzi effettivi.
     */
    public function getCartTotal(?Cart $cart = null): float
    {
        if (! $cart) {
            $cart = $this->getCart();
        }
        if (! $cart) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($cart->items as $item) {
            $effectivePrice = $item->product->effectivePrice();
            $modifier = $item->variant ? (float) $item->variant->price_modifier : 0.0;
            $total += ($effectivePrice + $modifier) * $item->quantity;
        }

        return round($total, 2);
    }

    /**
     * Restituisce il numero totale di pezzi nel carrello.
     */
    public function getItemCount(): int
    {
        $cart = $this->findCurrentCart();
        if (! $cart) {
            return 0;
        }

        return (int) $cart->items()->sum('quantity');
    }

    /**
     * Svuota tutti gli items del carrello corrente.
     */
    public function clearCart(): void
    {
        $cart = $this->findCurrentCart();
        if (! $cart) {
            return;
        }

        $cart->items()->delete();
        $this->invalidateCache();
    }

    /**
     * Al login, unisce il carrello sessione con quello utente.
     * Se lo stesso prodotto+variante esiste in entrambi, prende la quantità
     * maggiore (capped allo stock disponibile e al limite per prodotto).
     * Elimina il carrello sessione.
     *
     * @param  string|null  $sessionId  ID di sessione del carrello guest. Va passato
     *                                  esplicitamente quando il chiamante ha già
     *                                  rigenerato la sessione dopo il login: con il
     *                                  nuovo ID il carrello ospite non si troverebbe più.
     */
    public function mergeOnLogin(User $user, ?string $sessionId = null): void
    {
        DB::transaction(function () use ($user, $sessionId) {
            $sessionId ??= session()->getId();

            // Carrello sessione (guest)
            $sessionCart = Cart::where('session_id', $sessionId)
                ->whereNull('user_id')
                ->first();

            if (! $sessionCart) {
                return;
            }

            // Carrello utente esistente (o creane uno nuovo)
            $userCart = Cart::where('user_id', $user->id)->first();

            if (! $userCart) {
                // Assegna il carrello sessione all'utente
                $sessionCart->update([
                    'user_id' => $user->id,
                ]);

                $this->invalidateCache();

                return;
            }

            // Merge degli items
            $sessionItems = $sessionCart->items()->with(['product', 'variant'])->get();

            // Stesso limite per prodotto applicato da addItem/updateItemQuantity:
            // il merge non deve essere una scorciatoia per superarlo.
            $maxQty = (int) SiteSetting::get('shop.max_qty_per_product', 10);

            foreach ($sessionItems as $sessionItem) {
                $existingItem = $userCart->items()
                    ->where('product_id', $sessionItem->product_id)
                    ->where('product_variant_id', $sessionItem->product_variant_id)
                    ->first();

                $maxAllowed = min(
                    $this->getAvailableStock($sessionItem->product, $sessionItem->variant),
                    $maxQty
                );

                if ($existingItem) {
                    // Prendi la quantità maggiore, capped a stock e limite per prodotto
                    $mergedQty = min(
                        max($existingItem->quantity, $sessionItem->quantity),
                        $maxAllowed
                    );

                    // Stock esaurito: rimuovi l'item invece di lasciarlo a quantità 0
                    if ($mergedQty <= 0) {
                        $existingItem->delete();
                    } else {
                        $existingItem->update(['quantity' => $mergedQty]);
                    }
                } else {
                    // Sposta l'item nel carrello utente
                    $qty = min($sessionItem->quantity, $maxAllowed);

                    if ($qty > 0) {
                        $userCart->items()->create([
                            'product_id' => $sessionItem->product_id,
                            'product_variant_id' => $sessionItem->product_variant_id,
                            'quantity' => $qty,
                        ]);
                    }
                }
            }

            // Elimina il carrello sessione e i suoi items
            $sessionCart->items()->delete();
            $sessionCart->delete();

            $this->invalidateCache();
        });
    }

    /**
     * Valida la disponibilità stock di tutti gli items nel carrello.
     * Restituisce un array di items con stock insufficiente.
     *
     * @return array<int, array{item: CartItem, available: int, requested: int}>
     */
    public function validateStock(): array
    {
        $cart = $this->getCart();
        if (! $cart) {
            return [];
        }

        $issues = [];

        foreach ($cart->items as $item) {
            $availableStock = $this->getAvailableStock($item->product, $item->variant);

            if ($item->quantity > $availableStock) {
                $issues[] = [
                    'item' => $item,
                    'available' => $availableStock,
                    'requested' => $item->quantity,
                ];
            }
        }

        return $issues;
    }

    /**
     * Trova il carrello corrente per utente autenticato o sessione.
     */
    private function findCurrentCart(): ?Cart
    {
        if ($this->cartResolved) {
            return $this->resolvedCart;
        }

        $cart = null;

        if (auth()->check()) {
            $cart = Cart::where('user_id', auth()->id())->first();
        }

        if (! $cart) {
            $cart = Cart::where('session_id', session()->getId())
                ->where(function ($query) {
                    $query->where('expires_at', '>', now())
                        ->orWhereNull('expires_at');
                })
                ->first();
        }

        $this->resolvedCart = $cart;
        $this->cartResolved = true;

        return $cart;
    }

    /**
     * Invalida la cache in-process del carrello.
     * Chiamata dopo ogni operazione di mutazione.
     */
    public function invalidateCache(): void
    {
        $this->resolvedCart = null;
        $this->cartResolved = false;
    }

    /**
     * Rimuove dal carrello gli items il cui prodotto è inattivo o soft-deleted.
     * Restituisce il numero di items rimossi.
     */
    private function removeInactiveItems(Cart $cart): int
    {
        $items = $cart->items()->with('product')->get();
        $removedCount = 0;

        foreach ($items as $item) {
            if (! $item->product || ! $item->product->is_active || $item->product->trashed()) {
                $item->delete();
                $removedCount++;
            }
        }

        if ($removedCount > 0) {
            // Ricarica items dopo la pulizia
            $cart->unsetRelation('items');
        }

        return $removedCount;
    }

    /**
     * Ottiene lo stock disponibile per un prodotto/variante.
     * Per prodotti Variable usa lo stock della variante, per Simple lo stock del prodotto.
     */
    private function getAvailableStock(Product $product, ?ProductVariant $variant): int
    {
        if ($variant) {
            return (int) $variant->stock;
        }

        return (int) $product->stock;
    }
}
