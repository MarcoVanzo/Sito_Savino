<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
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
    public function index(Request $request): Response
    {
        $cart = $this->cartService->getCart();
        $total = $this->cartService->getCartTotal();
        $itemCount = $this->cartService->getItemCount();

        return Inertia::render('Public/Shop/Cart', [
            'cart' => $cart,
            'total' => $total,
            'itemCount' => $itemCount,
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
    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->cartService->getItemCount(),
            'total' => $this->cartService->getCartTotal(),
        ]);
    }

    /**
     * Restituisce i dati completi del carrello in JSON (per CartDrawer).
     */
    public function data(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart();

        $items = $cart?->items?->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'name' => $item->product?->name,
                'slug' => $item->product?->slug,
                'price' => $item->product ? ($item->product->effectivePrice() + ($item->variant?->price_modifier ?? 0)) : null,
                'variant_name' => $item->variant?->size,
                'image_url' => $item->product?->getImageUrl('card'),
            ];
        }) ?? collect();

        // Calcola totale e conteggio in-memory invece di ri-fetchare il cart
        $total = $cart?->items?->sum(function ($item) {
            if (! $item->product) return 0;
            return ($item->product->effectivePrice() + ($item->variant?->price_modifier ?? 0)) * $item->quantity;
        }) ?? 0;

        $count = $cart?->items?->sum('quantity') ?? 0;

        return response()->json([
            'items' => $items,
            'total' => round($total, 2),
            'count' => $count,
        ]);
    }
}
