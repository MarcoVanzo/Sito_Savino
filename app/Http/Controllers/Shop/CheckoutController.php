<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Webhooks\Traits\HandlesPaymentWebhooks;
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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    // L'incasso al ritorno dal gateway riusa, senza riscriverla, la stessa
    // registrazione del pagamento dei webhook: idempotenza sulla coppia
    // (ordine, transazione), riscarico della merce su un ordine annullato,
    // email di conferma e avviso al pannello. L'unico gateway che passa di qui
    // è PayPal — Stripe incassa da sé prima di rimandare il cliente indietro.
    use HandlesPaymentWebhooks;

    protected function getGatewayName(): string
    {
        return 'PayPal';
    }

    public function __construct(
        protected CartService $cartService,
        protected CheckoutService $checkoutService,
        protected AdminNotificationService $adminNotificationService,
    ) {}

    /**
     * Pagina di checkout.
     * Richiede un carrello non vuoto.
     */
    public function show(): Response|RedirectResponse
    {
        $cart = $this->cartService->getCart();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('shop.cart')
                ->with('error', __('messages.cart.empty'));
        }

        // Il cast `decimal:2` serializza gli importi come stringhe: nel client
        // la somma col totale carrello diventava una concatenazione
        // (4 + "7.90" = "47.90"). Si consegnano già come numeri.
        $shippingZones = ShippingZone::active()->ordered()->get()
            ->map(fn (ShippingZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'countries' => $zone->countries,
                'flat_rate' => (float) $zone->flat_rate,
                // Gia' ordinate e ripulite: il client sceglie la prima fascia
                // che contiene il peso, come fa il server.
                'weight_rates' => $zone->fasceOrdinate(),
                'free_threshold' => $zone->free_threshold !== null ? (float) $zone->free_threshold : null,
                'estimated_days_min' => $zone->estimated_days_min,
                'estimated_days_max' => $zone->estimated_days_max,
            ])
            ->values();

        // Payment gateways attivi dalla configurazione
        $activeGateways = SiteSetting::get('shop.active_payment_gateways', 'stripe,paypal,bank_transfer');
        $paymentGateways = collect(explode(',', $activeGateways))
            ->map(fn ($g) => trim($g))
            ->filter(fn ($g) => PaymentGateway::tryFrom($g) !== null)
            // Un gateway senza credenziali non si mostra: l'ordine verrebbe
            // creato e la merce riservata, e solo dopo il cliente finirebbe
            // sull'errore generico del checkout.
            ->filter(fn ($g) => PaymentGateway::from($g)->configurato())
            ->map(fn ($g) => [
                'value' => $g,
                'label' => PaymentGateway::from($g)->getLabel(),
                'icon' => PaymentGateway::from($g)->getIcon(),
            ])
            ->values();

        return Inertia::render('Public/Shop/Checkout', [
            'cart' => $cart,
            'cartTotal' => $this->cartService->getCartTotal(),
            'cartWeight' => $this->cartService->getCartWeight($cart),
            'itemCount' => $this->cartService->getItemCount(),
            'shippingZones' => $shippingZones,
            'paymentGateways' => $paymentGateways,
        ]);
    }

    /**
     * Processa il checkout e gestisce il pagamento.
     */
    public function store(StoreCheckoutRequest $request): RedirectResponse|SymfonyResponse
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

        if ($this->incassaAlRitornoDaPayPal($request, $order)) {
            $order->refresh()->load(['items.product', 'user']);
        }

        return Inertia::render('Public/Shop/CheckoutSuccess', [
            'order' => $order,
        ]);
    }

    /**
     * Incassa il pagamento PayPal appena il cliente torna sul sito.
     *
     * La cattura viveva solo dentro il webhook: se quello non arriva — non
     * registrato, dominio cambiato, firma che non verifica — il cliente ha
     * approvato su PayPal e l'incasso non parte mai; l'ordine resta in attesa
     * e dopo un'ora `order:check-unpaid` lo annulla. PayPal rimanda qui
     * l'identificativo dell'ordine nel parametro `token`: si tenta subito la
     * cattura, e il webhook — che di norma arriva lo stesso — trova il lavoro
     * fatto e si ferma sull'idempotenza.
     *
     * @return bool true se il pagamento è stato registrato adesso
     */
    private function incassaAlRitornoDaPayPal(Request $request, Order $order): bool
    {
        $token = trim((string) $request->query('token', ''));

        if ($token === ''
            || $order->payment_gateway !== PaymentGateway::PayPal
            || $order->payment_id !== null
            || $order->status !== OrderStatus::Pending) {
            return false;
        }

        // La pagina di conferma si ricarica da sola ogni cinque secondi finché
        // l'ordine è in attesa: senza questo freno ogni ricarica rifarebbe il
        // giro completo di chiamate al gateway. `add` è atomico, quindi para
        // anche due richieste arrivate insieme.
        if (! Cache::add("paypal:ritorno:{$order->id}", true, 30)) {
            return false;
        }

        try {
            $esito = app(PayPalPaymentService::class)->catturaAlRitorno($order, $token);

            if ($esito === null) {
                return false;
            }

            $this->handlePaymentCompleted($esito);

            return true;
        } catch (\Throwable $e) {
            // Il cliente deve vedere comunque la sua pagina di conferma: se la
            // cattura non riesce resta il webhook, e se manca anche quello
            // l'ordine si annulla da solo senza aver incassato nulla. Ma un
            // guasto qui va segnalato, non solo scritto su un log effimero.
            report($e);

            Log::error('PayPal: cattura al ritorno non riuscita', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Da dove arriva l'incasso registrato da questo controller. */
    protected function canaleDiIncasso(): string
    {
        return 'ritorno dal gateway';
    }

    /**
     * Pagina pagamento annullato.
     */
    public function cancel(string $orderToken): Response
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
    public function retryPayment(string $orderToken): RedirectResponse|SymfonyResponse
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

        // Un ordine con una transazione già registrata non si ripaga: resta in
        // attesa perché qualcuno deve guardarlo (per esempio un incasso di
        // importo diverso dal totale), e aprirgli una seconda sessione
        // significherebbe farlo pagare due volte.
        if ($order->payment_id !== null) {
            return redirect()->route('shop.checkout.success', ['orderToken' => $orderToken]);
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
     * Gestisce il pagamento Stripe: crea sessione e manda al gateway.
     */
    private function handleStripe(Order $order): SymfonyResponse
    {
        $stripeService = app(StripePaymentService::class);
        $url = $stripeService->createSession($order);

        return $this->vaiAlGateway($url);
    }

    /**
     * Gestisce il pagamento PayPal: crea sessione e manda al gateway.
     */
    private function handlePayPal(Order $order): SymfonyResponse
    {
        $paypalService = app(PayPalPaymentService::class);
        $url = $paypalService->createSession($order);

        return $this->vaiAlGateway($url);
    }

    /**
     * Porta il cliente fuori dal sito, sul gateway.
     *
     * Il modulo di checkout è un form Inertia: la POST parte come XHR con
     * l'header X-Inertia, e un 302 verso stripe.com o paypal.com viene
     * seguito dalla stessa XHR, che sbatte contro il CORS del gateway e
     * muore in console. L'ordine è però già stato creato e la merce
     * riservata: il cliente resta sulla pagina senza pagare e la merce
     * risulta venduta. Inertia vuole 409 + X-Inertia-Location per una
     * navigazione fuori dall'applicazione; fuori da Inertia (nessun JS,
     * un test, un crawler) `Inertia::location` degrada da sé al 302 di
     * prima.
     */
    private function vaiAlGateway(string $url): SymfonyResponse
    {
        return Inertia::location($url);
    }

    /**
     * Gestisce il bonifico bancario: redirect diretto alla pagina di successo
     * e invio email di conferma.
     */
    private function handleBankTransfer(Order $order): RedirectResponse
    {
        $recipientEmail = $order->user->email ?? $order->guest_email;

        if ($recipientEmail) {
            Mail::to($recipientEmail)->queue(new OrderConfirmation($order));
        }

        return redirect()->route('shop.checkout.success', ['orderToken' => $order->order_token])
            ->with('success', __('messages.checkout.success_bank'));
    }
}
