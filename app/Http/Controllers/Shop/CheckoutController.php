<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Models\ShopEvent;
use App\Models\SiteSetting;
use App\Services\AdminNotificationService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Payments\PayPalPaymentService;
use App\Services\Payments\StripePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutService $checkoutService,
        protected AdminNotificationService $adminNotificationService,
    ) {}

    /**
     * Pagina di checkout.
     * Richiede un carrello non vuoto.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $cart = $this->cartService->getCart();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('shop.cart')
                ->with('error', __('messages.cart.empty'));
        }

        $shippingZones = ShippingZone::active()->ordered()->get();

        // Payment gateways attivi dalla configurazione
        $activeGateways = SiteSetting::get('shop.active_payment_gateways', 'stripe,paypal,bank_transfer');
        $paymentGateways = collect(explode(',', $activeGateways))
            ->map(fn ($g) => trim($g))
            ->filter(fn ($g) => PaymentGateway::tryFrom($g) !== null)
            ->map(fn ($g) => [
                'value' => $g,
                'label' => PaymentGateway::from($g)->getLabel(),
                'icon' => PaymentGateway::from($g)->getIcon(),
            ])
            ->values();

        return Inertia::render('Public/Shop/Checkout', [
            'cart' => $cart,
            'cartTotal' => $this->cartService->getCartTotal(),
            'itemCount' => $this->cartService->getItemCount(),
            'shippingZones' => $shippingZones,
            'paymentGateways' => $paymentGateways,
        ]);
    }

    /**
     * Processa il checkout e gestisce il pagamento.
     */
    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'shipping_address' => ['required', 'string', 'max:500'],
            'billing_address' => ['required', 'string', 'max:500'],
            'country' => ['required', 'string', 'size:2'],
            'payment_gateway' => ['required', 'string', 'in:stripe,paypal,bank_transfer'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'privacy_accepted' => ['required', 'accepted'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        // Campi guest obbligatori se non autenticato
        if (! auth()->check()) {
            $rules['guest_name'] = ['required', 'string', 'max:255'];
            $rules['guest_email'] = ['required', 'email', 'max:255'];
            $rules['guest_phone'] = ['required', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        $cart = $this->cartService->getCart();
        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('shop.cart')
                ->with('error', __('messages.cart.empty'));
        }

        try {
            // Prepara i dati per il CheckoutService
            $orderData = [
                'shipping_address' => $validated['shipping_address'],
                'billing_address' => $validated['billing_address'],
                'country' => $validated['country'],
                'payment_gateway' => $validated['payment_gateway'],
                'coupon_code' => $validated['coupon_code'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'privacy_accepted_at' => now(),
                'guest_name' => $validated['guest_name'] ?? null,
                'guest_email' => $validated['guest_email'] ?? (auth()->user()?->email),
                'guest_phone' => $validated['guest_phone'] ?? null,
                'user_id' => auth()->id(),
            ];

            $order = $this->checkoutService->createOrder(
                $cart,
                $orderData,
                auth()->user(),
            );

            // Track begin_checkout event
            ShopEvent::create([
                'event_type' => 'begin_checkout',
                'viewable_type' => Order::class,
                'viewable_id' => $order->id,
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
                'ip_address' => $request->ip(),
            ]);

            // Gestisci il pagamento in base al gateway selezionato
            $gateway = PaymentGateway::from($validated['payment_gateway']);

            // Notifica admin solo per bonifico (pagamento differito) —
            // per Stripe/PayPal la notifica arriva dal webhook dopo il pagamento effettivo
            if ($gateway === PaymentGateway::BankTransfer) {
                $this->adminNotificationService->notifyNewOrder($order);
            }

            return match ($gateway) {
                PaymentGateway::Stripe => $this->handleStripe($order),
                PaymentGateway::PayPal => $this->handlePayPal($order),
                PaymentGateway::BankTransfer => $this->handleBankTransfer($order),
            };
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Errore durante il checkout', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', __('messages.checkout.error'));
        }
    }

    /**
     * Pagina di conferma ordine.
     */
    public function success(Request $request, string $orderToken): Response
    {
        $order = Order::where('order_token', $orderToken)
            ->with(['items.product', 'user'])
            ->firstOrFail();

        return Inertia::render('Public/Shop/CheckoutSuccess', [
            'order' => $order,
        ]);
    }

    /**
     * Pagina pagamento annullato.
     */
    public function cancel(Request $request, string $orderToken): Response
    {
        $order = Order::where('order_token', $orderToken)->firstOrFail();

        $canRetry = $order->status === OrderStatus::Pending;

        return Inertia::render('Public/Shop/CheckoutCancel', [
            'order' => $order,
            'canRetry' => $canRetry,
        ]);
    }

    /**
     * Gestisce il pagamento Stripe: crea sessione e redirect.
     */
    private function handleStripe(Order $order): RedirectResponse
    {
        $stripeService = app(StripePaymentService::class);
        $url = $stripeService->createSession($order);

        return redirect()->away($url);
    }

    /**
     * Gestisce il pagamento PayPal: crea sessione e redirect.
     */
    private function handlePayPal(Order $order): RedirectResponse
    {
        $paypalService = app(PayPalPaymentService::class);
        $url = $paypalService->createSession($order);

        return redirect()->away($url);
    }

    /**
     * Gestisce il bonifico bancario: redirect diretto alla pagina di successo
     * e invio email di conferma.
     */
    private function handleBankTransfer(Order $order): RedirectResponse
    {
        $recipientEmail = $order->user?->email ?? $order->guest_email;

        if ($recipientEmail) {
            Mail::to($recipientEmail)->send(new OrderConfirmation($order));
        }

        return redirect()->route('shop.checkout.success', ['orderToken' => $order->order_token])
            ->with('success', __('messages.checkout.success_bank'));
    }
}
