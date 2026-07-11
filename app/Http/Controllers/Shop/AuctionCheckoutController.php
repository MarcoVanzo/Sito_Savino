<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingZone;
use App\Models\StockMovement;
use App\Services\AuctionService;
use App\Services\Payments\StripePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuctionCheckoutController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
    ) {}

    /**
     * Mostra la pagina di checkout per il vincitore dell'asta.
     */
    public function show(string $token): Response|RedirectResponse
    {
        $auction = Auction::where('winner_checkout_token', $token)->first();

        if (! $auction) {
            abort(404);
        }

        // Solo il vincitore può accedere al checkout
        if (auth()->id() !== $auction->winner_user_id) {
            abort(403, __('messages.auction_checkout.not_winner'));
        }

        // Verifica che la deadline non sia scaduta
        if ($auction->winner_checkout_deadline && $auction->winner_checkout_deadline->isPast()) {
            return Inertia::render('Public/Shop/Auctions/CheckoutExpired', [
                'auction' => $auction->only(['id', 'title', 'winner_checkout_deadline']),
            ]);
        }

        // Se l'ordine esiste già, redirect alla pagina di successo
        $existingOrder = $this->auctionService->getWinnerOrder($auction);

        if ($existingOrder) {
            return redirect()->route('shop.auction-checkout.success', ['token' => $token])
                ->with('info', __('messages.auction_checkout.already_ordered'));
        }

        // Carica l'asta con product e media
        $auction->load(['product.media']);

        $shippingZones = ShippingZone::active()->ordered()->get();

        return Inertia::render('Public/Shop/Auctions/Checkout', [
            'auction' => $auction,
            'product' => $auction->product,
            'shippingZones' => $shippingZones,
            'checkoutDeadline' => $auction->winner_checkout_deadline?->toIso8601String(),
            'winningBid' => (float) $auction->current_bid,
        ]);
    }

    /**
     * Processa il checkout dell'asta e avvia il pagamento Stripe.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $auction = Auction::where('winner_checkout_token', $token)->first();

        if (! $auction) {
            abort(404);
        }

        if (auth()->id() !== $auction->winner_user_id) {
            abort(403, __('messages.auction_checkout.not_winner'));
        }

        if ($auction->winner_checkout_deadline && $auction->winner_checkout_deadline->isPast()) {
            return redirect()->route('shop.auction-checkout.show', ['token' => $token])
                ->with('error', __('messages.auction_checkout.deadline_expired'));
        }

        // Se l'ordine esiste già, redirect alla pagina di successo
        $existingOrder = $this->auctionService->getWinnerOrder($auction);

        if ($existingOrder) {
            return redirect()->route('shop.auction-checkout.success', ['token' => $token]);
        }

        // Sanitizza input prima della validazione (allineato a StoreCheckoutRequest)
        if ($request->has('notes') && $request->notes !== null) {
            $request->merge(['notes' => strip_tags($request->notes)]);
        }
        if ($request->has('codice_fiscale') && $request->codice_fiscale !== null) {
            $request->merge(['codice_fiscale' => strtoupper(trim($request->codice_fiscale))]);
        }

        $validated = $request->validate([
            'shipping_first_name' => ['required', 'string', 'max:100'],
            'shipping_last_name' => ['required', 'string', 'max:100'],
            'shipping_street' => ['required', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_zip_code' => ['required', 'string', 'max:20'],
            'shipping_province' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'phone' => ['required', 'string', 'max:30'],
            'codice_fiscale' => ['nullable', 'string', 'size:16', 'regex:/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i'],
            'billing_same_as_shipping' => ['required', 'boolean'],
            'billing_first_name' => ['required_if:billing_same_as_shipping,false', 'nullable', 'string', 'max:100'],
            'billing_last_name' => ['required_if:billing_same_as_shipping,false', 'nullable', 'string', 'max:100'],
            'billing_street' => ['required_if:billing_same_as_shipping,false', 'nullable', 'string', 'max:255'],
            'billing_city' => ['required_if:billing_same_as_shipping,false', 'nullable', 'string', 'max:100'],
            'billing_zip_code' => ['required_if:billing_same_as_shipping,false', 'nullable', 'string', 'max:20'],
            'billing_province' => ['required_if:billing_same_as_shipping,false', 'nullable', 'string', 'max:100'],
            'privacy_accepted' => ['required', 'accepted'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Validazione condizionale: CAP italiano e codice fiscale obbligatorio per Italia
        if ($validated['country'] === 'IT') {
            if (! preg_match('/^\d{5}$/', $validated['shipping_zip_code'])) {
                return back()->withErrors(['shipping_zip_code' => __('validation.zip_code_it')]);
            }
            if (empty($validated['codice_fiscale'])) {
                return back()->withErrors(['codice_fiscale' => __('messages.auction.codice_fiscale_required')]);
            }
        }

        $lock = Cache::lock('auction_checkout_lock:'.auth()->id(), 30);
        if (! $lock->get()) {
            return back()->withErrors(['general' => __('messages.auction.already_processing')]);
        }

        try {
            // Valida zona spedizione
            $shippingZone = ShippingZone::findByCountry($validated['country']);
            if (! $shippingZone) {
                return back()->withErrors(['country' => __('messages.checkout.country_not_served')]);
            }

            $order = DB::transaction(function () use ($auction, $validated, $token, $shippingZone) {
                $user = auth()->user();
                $winningBid = (float) $auction->current_bid;

                // Struttura indirizzo spedizione
                $shippingAddress = [
                    'first_name' => $validated['shipping_first_name'],
                    'last_name' => $validated['shipping_last_name'],
                    'street' => $validated['shipping_street'],
                    'city' => $validated['shipping_city'],
                    'zip_code' => $validated['shipping_zip_code'],
                    'province' => $validated['shipping_province'],
                ];

                // Struttura indirizzo fatturazione
                $billingAddress = $validated['billing_same_as_shipping']
                    ? $shippingAddress
                    : [
                        'first_name' => $validated['billing_first_name'],
                        'last_name' => $validated['billing_last_name'],
                        'street' => $validated['billing_street'],
                        'city' => $validated['billing_city'],
                        'zip_code' => $validated['billing_zip_code'],
                        'province' => $validated['billing_province'],
                    ];

                $shippingCost = $shippingZone->calculateShippingCost($winningBid);

                $totalPrice = round($winningBid + $shippingCost, 2);

                // Crea l'ordine — order_token === winner_checkout_token
                $order = Order::create([
                    'user_id' => $user->id,
                    'order_token' => $token,
                    'total_price' => $totalPrice,
                    'shipping_address' => $shippingAddress,
                    'billing_address' => $billingAddress,
                    'country' => $validated['country'],
                    'billing_country' => $validated['country'],
                    'phone' => $validated['phone'],
                    'codice_fiscale' => $validated['codice_fiscale'] ?? null,
                    'payment_gateway' => PaymentGateway::Stripe,
                    'shipping_cost' => $shippingCost,
                    'notes' => $validated['notes'] ?? null,
                    'privacy_accepted_at' => now(),
                ]);

                // status is not mass-assignable (security), set it explicitly
                $order->forceFill(['status' => OrderStatus::Pending])->save();

                // Crea l'item dell'ordine (un solo prodotto d'asta)
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $auction->product_id,
                    'quantity' => 1,
                    'price_at_time_of_purchase' => $winningBid,
                ]);

                // Riserva lo stock del prodotto d'asta
                StockMovement::create([
                    'product_id' => $auction->product_id,
                    'product_variant_id' => null,
                    'order_id' => $order->id,
                    'quantity' => -1,
                    'type' => StockMovementType::Sale,
                    'notes' => "Ordine #{$order->id} — asta #{$auction->id}",
                ]);

                return $order;
            });

            // Pagamento forzato su Stripe
            $stripeService = app(StripePaymentService::class);
            $url = $stripeService->createSession($order);

            return redirect()->away($url);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Errore durante il checkout asta', [
                'auction_id' => $auction->id,
                'token' => $token,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', __('messages.checkout.error'));
        } finally {
            $lock->release();
        }
    }

    /**
     * Pagina di conferma ordine asta completato.
     */
    public function success(string $token): Response
    {
        $auction = Auction::where('winner_checkout_token', $token)
            ->with(['product.media'])
            ->firstOrFail();

        if (auth()->id() !== $auction->winner_user_id) {
            abort(403);
        }

        $order = Order::where('order_token', $token)
            ->with(['items.product'])
            ->firstOrFail();

        return Inertia::render('Public/Shop/Auctions/CheckoutSuccess', [
            'auction' => $auction,
            'order' => $order,
        ]);
    }

    /**
     * Pagina pagamento asta annullato — consente retry.
     */
    public function cancel(string $token): Response
    {
        $auction = Auction::where('winner_checkout_token', $token)->firstOrFail();

        if (auth()->id() !== $auction->winner_user_id) {
            abort(403);
        }

        $order = Order::where('order_token', $token)->firstOrFail();
        $canRetry = $order->status === OrderStatus::Pending;

        return Inertia::render('Public/Shop/Auctions/CheckoutCancel', [
            'auction' => $auction,
            'order' => $order,
            'canRetry' => $canRetry,
            'retryUrl' => $canRetry
                ? route('shop.auction-checkout.show', ['token' => $token])
                : null,
        ]);
    }
}
