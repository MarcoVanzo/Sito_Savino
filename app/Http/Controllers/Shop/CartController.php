<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\SiteSetting;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
    ) {}

    /**
     * Mostra il contenuto del carrello.
     */
    public function index(): Response
    {
        $cart = $this->cartService->getCart();
        $total = $this->cartService->getCartTotal();
        $itemCount = $this->cartService->getItemCount();

        $mappedItems = $cart?->items?->map(fn ($item) => $this->mapCartItem($item)) ?? collect();

        return Inertia::render('Public/Shop/Cart', [
            'cart' => [
                'id' => $cart?->id,
                'items' => $mappedItems,
            ],
            'total' => $total,
            'itemCount' => $itemCount,
            'freeShippingThreshold' => (float) SiteSetting::get('shop.free_shipping_threshold', 50),
        ]);
    }

    /**
     * Aggiunge un prodotto al carrello.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ]);

        try {
            $this->cartService->addItem(
                $validated['product_id'],
                $validated['quantity'],
                $validated['variant_id'] ?? null,
            );

            return back()->with('success', __('messages.cart.added'));
        } catch (\OverflowException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Aggiorna la quantità di un item nel carrello.
     */
    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $cart = $this->cartService->getOrCreateCart();
        abort_unless($cartItem->cart_id === $cart->id, 403);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $this->cartService->updateItemQuantity(
                $cartItem->id,
                $validated['quantity'],
            );

            return back()->with('success', __('messages.cart.updated'));
        } catch (\OverflowException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Rimuove un item dal carrello.
     */
    public function destroy(CartItem $cartItem): RedirectResponse
    {
        $cart = $this->cartService->getOrCreateCart();
        abort_unless($cartItem->cart_id === $cart->id, 403);

        try {
            $this->cartService->removeItem($cartItem->id);

            return back()->with('success', __('messages.cart.removed'));
        } catch (\OverflowException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Restituisce il conteggio carrello in JSON per aggiornamento badge AJAX.
     */
    public function count(): JsonResponse
    {
        return response()->json([
            'count' => $this->cartService->getItemCount(),
            'total' => $this->cartService->getCartTotal(),
        ]);
    }

    /**
     * Restituisce i dati completi del carrello in JSON (per CartDrawer).
     */
    public function data(): JsonResponse
    {
        $cart = $this->cartService->getCart();

        $items = $cart?->items?->map(fn ($item) => $this->mapCartItem($item)) ?? collect();

        // Calcola totale e conteggio in-memory invece di ri-fetchare il cart
        $total = $cart?->items?->sum(function ($item) {
            if (! $item->product) {
                return 0;
            }

            return ($item->product->effectivePrice() + ($item->variant->price_modifier ?? 0)) * $item->quantity;
        }) ?? 0;

        $count = $cart?->items?->sum('quantity') ?? 0;

        return response()->json([
            'items' => $items,
            'total' => round($total, 2),
            'count' => $count,
        ]);
    }

    /**
     * Mappa un CartItem in un array normalizzato.
     * Usato sia da index() (Inertia) che da data() (JSON).
     */
    private function mapCartItem($item): array
    {
        $availableStock = $item->variant
            ? (int) $item->variant->stock
            : (int) ($item->product->stock ?? 0);

        $variantStr = $item->variant
            ? trim(implode(' - ', array_filter([$item->variant->size, $item->variant->color])))
            : null;

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'name' => $item->product?->name,
            'slug' => $item->product?->slug,
            'price' => $item->product ? ($item->product->effectivePrice() + ($item->variant->price_modifier ?? 0)) : null,
            'variant' => $variantStr,
            'variant_name' => $variantStr,
            'image' => $item->product?->getImageUrl('card'),
            'image_url' => $item->product?->getImageUrl('card'),
            'stock' => $availableStock,
            'stock_warning' => $item->quantity > $availableStock,
        ];
    }
}
