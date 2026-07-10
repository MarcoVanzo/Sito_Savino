<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCheckoutRequest;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
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
    public function store(StoreCheckoutRequest $request): RedirectResponse
    {
        $cart = $this->cartService->getCart();
        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('shop.cart')
                ->with('error', __('messages.cart.empty'));
        }

        $lockKey = 'checkout_lock:'.session()->getId();
        $lock = Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            return back()->with('error', __('messages.checkout.already_processing'));
        }

        try {
            // Prepara i dati per il CheckoutService dalla FormRequest
            $orderData = $request->toOrderData();

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
            $gateway = PaymentGateway::from($orderData['payment_gateway']);

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
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Errore durante il checkout', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', __('messages.checkout.error'));
        } finally {
            $lock->forceRelease();
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

        // Se autenticato, verifica che l'ordine appartenga all'utente corrente
        if (auth()->check() && $order->user_id !== null && $order->user_id !== auth()->id()) {
            abort(403);
        }

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

        // Se autenticato, verifica che l'ordine appartenga all'utente corrente
        if (auth()->check() && $order->user_id !== null && $order->user_id !== auth()->id()) {
            abort(403);
        }

        $canRetry = $order->status === OrderStatus::Pending
            && in_array($order->payment_gateway, [PaymentGateway::Stripe, PaymentGateway::PayPal]);

        return Inertia::render('Public/Shop/CheckoutCancel', [
            'order' => $order,
            'canRetry' => $canRetry,
        ]);
    }

    /**
     * Riprova il pagamento per un ordine Pending.
     * Crea una nuova sessione Stripe/PayPal per l'ordine esistente
     * senza richiedere un nuovo checkout (il carrello è già stato svuotato).
     */
    public function retryPayment(Request $request, string $orderToken): RedirectResponse
    {
        $order = Order::where('order_token', $orderToken)->firstOrFail();

        // Solo ordini Pending con gateway digitale possono fare retry
        if ($order->status !== OrderStatus::Pending) {
            return redirect()->route('shop.checkout.cancel', ['orderToken' => $orderToken])
                ->with('error', __('messages.checkout.retry_not_available'));
        }

        // Verifica proprietà ordine
        if (auth()->check()) {
            // Utente loggato: può fare retry solo sui propri ordini registrati
            if ($order->user_id === null || $order->user_id !== auth()->id()) {
                abort(403);
            }
        } elseif ($order->user_id !== null) {
            // Guest: non può fare retry su ordini di utenti registrati
            abort(403);
        }

        try {
            return match ($order->payment_gateway) {
                PaymentGateway::Stripe => $this->handleStripe($order),
                PaymentGateway::PayPal => $this->handlePayPal($order),
                default => redirect()->route('shop.checkout.cancel', ['orderToken' => $orderToken])
                    ->with('error', __('messages.checkout.retry_not_available')),
            };
        } catch (\Exception $e) {
            Log::error('Errore retry pagamento', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('shop.checkout.cancel', ['orderToken' => $orderToken])
                ->with('error', __('messages.checkout.error'));
        }
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
            Mail::to($recipientEmail)->queue(new OrderConfirmation($order));
        }

        return redirect()->route('shop.checkout.success', ['orderToken' => $order->order_token])
            ->with('success', __('messages.checkout.success_bank'));
    }
}
