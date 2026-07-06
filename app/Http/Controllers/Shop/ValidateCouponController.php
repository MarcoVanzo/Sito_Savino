<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidateCouponController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutService $checkoutService,
    ) {}

    /**
     * Valida un codice coupon e restituisce lo sconto calcolato.
     * Endpoint AJAX per la validazione pre-submit nel checkout.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:50'],
            'guest_email' => ['nullable', 'email', 'max:255'],
        ]);

        $cart = $this->cartService->getCart();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json([
                'valid' => false,
                'message' => __('messages.checkout.cart_empty'),
            ], 422);
        }

        try {
            $subtotal = $this->cartService->getCartTotal($cart);

            $result = $this->checkoutService->applyCoupon(
                $validated['coupon_code'],
                $subtotal,
                auth()->id(),
                $validated['guest_email'] ?? null,
            );

            return response()->json([
                'valid' => true,
                'discount' => $result['discount'],
                'coupon_code' => $result['coupon']->code,
                'message' => __('messages.checkout.coupon_applied', [
                    'discount' => number_format($result['discount'], 2),
                ]),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'valid' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
