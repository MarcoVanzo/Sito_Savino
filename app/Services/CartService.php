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

        return Cart::create($attributes);
    }

    /**
     * Aggiunge un prodotto al carrello.
     * Se l'item esiste già (stesso prodotto+variante), aggiorna la quantità.
     *
     * @throws \InvalidArgumentException
     * @throws \OverflowException
     */
    public function addItem(int $productId, int $quantity = 1, ?int $variantId = null): CartItem
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

                return $existingItem->fresh();
            }

            return $cart->items()->create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
            ]);
        });
    }

    /**
     * Aggiorna la quantità di un item nel carrello.
     * Se la quantità è 0, rimuove l'item.
     *
     * @throws \OverflowException
     */
    public function updateItemQuantity(int $cartItemId, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cartItemId, $quantity) {
            $cart = $this->findCurrentCart();
            if (! $cart) {
                throw new \RuntimeException(__('messages.cart.not_found'));
            }

            $item = $cart->items()->with(['product', 'variant'])->findOrFail($cartItemId);

            // Se quantità 0, rimuovi l'item
            if ($quantity <= 0) {
                $item->delete();

                return $item;
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
    }

    /**
     * Restituisce il carrello corrente con gli items caricati.
     */
    public function getCart(): ?Cart
    {
        $cart = $this->findCurrentCart();

        if ($cart) {
            $cart->load(['items.product.media', 'items.variant']);
        }

        return $cart;
    }

    /**
     * Calcola il totale del carrello usando i prezzi effettivi.
     */
    public function getCartTotal(?Cart $cart = null): float
    {
        if (!$cart) {
            $cart = $this->getCart();
        }
        if (!$cart) {
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
    }

    /**
     * Al login, unisce il carrello sessione con quello utente.
     * Se lo stesso prodotto+variante esiste in entrambi, prende la quantità
     * maggiore (capped allo stock disponibile). Elimina il carrello sessione.
     */
    public function mergeOnLogin(User $user): void
    {
        DB::transaction(function () use ($user) {
            $sessionId = session()->getId();

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

                return;
            }

            // Merge degli items
            $sessionItems = $sessionCart->items()->with(['product', 'variant'])->get();

            foreach ($sessionItems as $sessionItem) {
                $existingItem = $userCart->items()
                    ->where('product_id', $sessionItem->product_id)
                    ->where('product_variant_id', $sessionItem->product_variant_id)
                    ->first();

                $availableStock = $this->getAvailableStock(
                    $sessionItem->product,
                    $sessionItem->variant
                );

                if ($existingItem) {
                    // Prendi la quantità maggiore, capped allo stock
                    $mergedQty = min(
                        max($existingItem->quantity, $sessionItem->quantity),
                        $availableStock
                    );
                    $existingItem->update(['quantity' => $mergedQty]);
                } else {
                    // Sposta l'item nel carrello utente
                    $qty = min($sessionItem->quantity, $availableStock);
                    $userCart->items()->create([
                        'product_id' => $sessionItem->product_id,
                        'product_variant_id' => $sessionItem->product_variant_id,
                        'quantity' => $qty,
                    ]);
                }
            }

            // Elimina il carrello sessione e i suoi items
            $sessionCart->items()->delete();
            $sessionCart->delete();
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
        if (auth()->check()) {
            $cart = Cart::where('user_id', auth()->id())->first();
            if ($cart) {
                return $cart;
            }
        }

        return Cart::where('session_id', session()->getId())
            ->where(function ($query) {
                $query->where('expires_at', '>', now())
                    ->orWhereNull('expires_at');
            })
            ->first();
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
