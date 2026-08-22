<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\StockMovementType;
use App\Exceptions\ShippingUnavailableException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
    ) {}

    /**
     * Valida tutti i prerequisiti per il checkout.
     *
     * Returns the pre-validated coupon result to avoid a second lookup
     * during order creation.
     *
     * @return array{coupon_result: array|null}
     *
     * @throws ValidationException
     */
    public function validateCheckout(Cart $cart, array $data): array
    {
        $errors = [];

        // 1. Carrello vuoto
        $cart->loadMissing('items.product', 'items.variant');
        if ($cart->items->isEmpty()) {
            $errors['cart'] = [__('messages.checkout.cart_empty')];
        }

        // 2. Controllo stock
        if ($cart->items->isNotEmpty()) {
            $stockIssues = $this->cartService->validateStock();
            if (! empty($stockIssues)) {
                $messages = [];
                foreach ($stockIssues as $issue) {
                    $productName = $issue['item']->product->name;
                    $messages[] = __('messages.checkout.stock_issue', ['product' => $productName, 'available' => $issue['available'], 'requested' => $issue['requested']]);
                }
                $errors['stock'] = $messages;
            }
        }

        // 3. Paese servito
        if (! empty($data['country'])) {
            $zone = ShippingZone::findByCountry($data['country']);
            if (! $zone) {
                $errors['country'] = [__('messages.checkout.country_not_served')];
            }
        } else {
            $errors['country'] = [__('messages.checkout.country_required')];
        }

        // 4. Coupon valido (se fornito) — pre-validate and cache result
        $couponResult = null;
        if (! empty($data['coupon_code'])) {
            try {
                $subtotal = $this->calculateSubtotal($cart);
                $couponResult = $this->applyCoupon(
                    $data['coupon_code'],
                    $subtotal,
                    $data['user_id'] ?? null,
                    $data['guest_email'] ?? null
                );
            } catch (\Exception $e) {
                $errors['coupon_code'] = [$e->getMessage()];
            }
        }

        // 5. Privacy accettata
        if (empty($data['privacy_accepted_at'])) {
            $errors['privacy_accepted_at'] = [__('messages.checkout.privacy_required')];
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return ['coupon_result' => $couponResult];
    }

    /**
     * Crea un ordine completo dal carrello.
     * Tutti i prezzi vengono ricalcolati dal DB, mai dal client.
     *
     * @throws ValidationException
     */
    public function createOrder(Cart $cart, array $data, ?User $user = null): Order
    {
        return DB::transaction(function () use ($cart, $data, $user) {
            // 1. Valida il checkout and get pre-validated coupon
            $validationResult = $this->validateCheckout($cart, $data);

            // 2. Carica items con relazioni
            $cart->load(['items.product', 'items.variant']);

            // 2b. Lock prodotti/varianti e ri-valida stock (previene race condition)
            $this->validateStockWithLock($cart);

            // 3. Calcola subtotale da DB
            $subtotal = $this->calculateSubtotal($cart);

            // 4. Calcola spedizione
            $shippingCost = $this->calculateShipping($data['country'], $subtotal);

            // 5. Reuse coupon result from validation (no double-lookup)
            $couponId = null;
            $couponDiscount = 0.0;
            $coupon = null;

            if ($validationResult['coupon_result'] !== null) {
                $couponResult = $validationResult['coupon_result'];
                $couponDiscount = $couponResult['discount'];
                $coupon = $couponResult['coupon'];
                $couponId = $coupon->id;
            }

            // 6. Calcola totale finale
            $totalPrice = max(0, $subtotal + $shippingCost - $couponDiscount);

            // 7. Crea l'ordine
            $order = Order::create([
                'user_id' => $user?->id,
                'order_token' => Str::uuid()->toString(),
                'guest_name' => $data['guest_name'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'country' => $data['country'],
                'billing_country' => $data['billing_country'] ?? $data['country'],
                'shipping_address' => $data['shipping_address'],
                'billing_address' => $data['billing_address'],
                'payment_gateway' => PaymentGateway::from($data['payment_gateway']),
                'total_price' => round($totalPrice, 2),
                'shipping_cost' => round($shippingCost, 2),
                'coupon_id' => $couponId,
                'coupon_discount' => round($couponDiscount, 2),
                'notes' => $data['notes'] ?? null,
                'codice_fiscale' => $data['codice_fiscale'] ?? null,
                'phone' => $data['phone'] ?? null,
                'privacy_accepted_at' => $data['privacy_accepted_at'],
            ]);

            // status is not mass-assignable (security), set it explicitly
            $order->forceFill(['status' => OrderStatus::Pending])->save();

            // 8. Crea OrderItems con snapshot dei prezzi
            foreach ($cart->items as $cartItem) {
                $effectivePrice = $cartItem->product->effectivePrice();
                $modifier = $cartItem->variant ? (float) $cartItem->variant->price_modifier : 0.0;
                $unitPrice = $effectivePrice + $modifier;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'product_variant_id' => $cartItem->product_variant_id,
                    'quantity' => $cartItem->quantity,
                    'price_at_time_of_purchase' => round($unitPrice, 2),
                ]);
            }

            // 9. Registra CouponUsage e incrementa contatore
            if ($coupon) {
                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'user_id' => $user?->id,
                    'guest_email' => $data['guest_email'] ?? null,
                    'used_at' => now(),
                ]);

                $coupon->incrementUsage();
            }

            // 10. Riserva lo stock istantaneamente
            foreach ($cart->items as $cartItem) {
                StockMovement::create([
                    'product_id' => $cartItem->product_id,
                    'product_variant_id' => $cartItem->product_variant_id,
                    'order_id' => $order->id,
                    'quantity' => -abs($cartItem->quantity),
                    'type' => StockMovementType::Sale,
                    'notes' => "Ordine #{$order->id} — vendita (riservato al checkout)",
                ]);
            }

            // 11. Svuota il carrello
            $this->cartService->clearCart();

            // Aggiorna il telefono nel profilo utente se fornito
            if ($user && ! empty($data['phone'])) {
                $user->update(['phone' => $data['phone']]);
            }

            return $order->load('items.product');
        });
    }

    /**
     * Calcola il costo di spedizione per un paese e subtotale.
     *
     * @throws ShippingUnavailableException
     */
    public function calculateShipping(string $countryCode, float $subtotal): float
    {
        $zone = ShippingZone::findByCountry($countryCode);

        if (! $zone) {
            throw new ShippingUnavailableException(
                __('messages.checkout.country_not_served')
            );
        }

        return $zone->calculateShippingCost($subtotal);
    }

    /**
     * Applica un codice coupon e restituisce lo sconto calcolato.
     *
     * @return array{discount: float, coupon: Coupon}
     *
     * @throws \InvalidArgumentException
     */
    public function applyCoupon(string $code, float $subtotal, ?int $userId = null, ?string $guestEmail = null): array
    {
        $coupon = Coupon::byCode($code)->lockForUpdate()->first();

        if (! $coupon) {
            throw new \InvalidArgumentException(__('messages.checkout.invalid_coupon'));
        }

        if (! $coupon->isValidForOrder($subtotal, $userId, $guestEmail)) {
            throw new \InvalidArgumentException(__('messages.checkout.coupon_not_applicable'));
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return [
            'discount' => round($discount, 2),
            'coupon' => $coupon,
        ];
    }

    /**
     * Calcola il subtotale del carrello dai prezzi in DB.
     */
    private function calculateSubtotal(Cart $cart): float
    {
        $cart->loadMissing('items.product', 'items.variant');
        $subtotal = 0.0;

        foreach ($cart->items as $item) {
            $effectivePrice = $item->product->effectivePrice();
            $modifier = $item->variant ? (float) $item->variant->price_modifier : 0.0;
            $subtotal += ($effectivePrice + $modifier) * $item->quantity;
        }

        return round($subtotal, 2);
    }

    /**
     * Blocca i prodotti/varianti con lockForUpdate() e ri-valida lo stock
     * all'interno della transazione per prevenire overselling da checkout concorrenti.
     *
     * @throws ValidationException
     */
    private function validateStockWithLock(Cart $cart): void
    {
        $items = $cart->items;

        if ($items->isEmpty()) {
            return;
        }

        // Raccogli tutti gli ID unici
        $productIds = $items->pluck('product_id')->unique()->filter()->values()->all();
        $variantIds = $items->pluck('product_variant_id')->unique()->filter()->values()->all();

        // Lock con SELECT ... FOR UPDATE
        $lockedProducts = Product::whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $lockedVariants = collect();
        if (! empty($variantIds)) {
            $lockedVariants = ProductVariant::whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
        }

        // Valida stock per ogni item del carrello
        $errors = [];
        foreach ($items as $item) {
            $product = $lockedProducts->get($item->product_id);
            $variant = $item->product_variant_id
                ? $lockedVariants->get($item->product_variant_id)
                : null;

            $availableStock = $variant
                ? (int) $variant->stock
                : (int) ($product->stock ?? 0);

            if ($item->quantity > $availableStock) {
                $productName = $product->name ?? 'Unknown';
                $errors[] = __('messages.checkout.stock_issue', [
                    'product' => $productName,
                    'available' => $availableStock,
                    'requested' => $item->quantity,
                ]);
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages([
                'stock' => $errors,
            ]);
        }
    }
}
