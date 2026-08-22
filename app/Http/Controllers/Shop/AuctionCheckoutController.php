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
    /**
     * Dati richiesti al vincitore. Sono gli stessi del checkout dello shop,
     * meno il metodo di pagamento: le aste si pagano solo con carta.
     */
    private const REGOLE = [
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
    ];

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

        // Solo un ordine EFFETTIVAMENTE PAGATO chiude il checkout: prima bastava
        // l'esistenza dell'ordine, così chi tornava indietro da Stripe restava
        // bloccato per sempre sulla pagina "ordine già effettuato".
        $existingOrder = $this->auctionService->getWinnerOrder($auction);

        if ($existingOrder && $existingOrder->paid_at !== null) {
            return redirect()->route('shop.auction-checkout.success', ['token' => $token])
                ->with('info', __('messages.auction_checkout.already_ordered'));
        }

        // Ordine ancora da pagare e deadline aperta: riapri una sessione di
        // pagamento sull'ordine esistente (stesso order_token), senza crearne
        // uno nuovo né duplicare la riserva di stock.
        if ($existingOrder && $existingOrder->status === OrderStatus::Pending) {
            $retryUrl = $this->openPaymentSession($existingOrder);

            if ($retryUrl) {
                return redirect()->away($retryUrl);
            }
        } elseif ($existingOrder) {
            // Ordine annullato/rimborsato o già in lavorazione: non è pagabile e
            // non se ne può creare un altro (un solo ordine per asta e utente).
            return Inertia::render('Public/Shop/Auctions/CheckoutExpired', [
                'auction' => $auction->only(['id', 'title', 'winner_checkout_deadline']),
            ]);
        }

        // Carica l'asta con product e media
        $auction->load(['product.media']);

        $shippingZones = ShippingZone::active()->ordered()->get();

        return Inertia::render('Public/Shop/Auctions/Checkout', [
            'auction' => $auction,
            'product' => $auction->product,
            'shippingZones' => $shippingZones,
            'checkoutDeadline' => $auction->winner_checkout_deadline?->toIso8601String(),
            'winningBid' => $this->auctionService->winningAmountFor($auction),
        ]);
    }

    /**
     * Apre una nuova sessione Stripe su un ordine già esistente e non pagato.
     * Restituisce l'URL di pagamento, oppure null se Stripe non risponde
     * (in quel caso il chiamante mostra di nuovo il form di checkout).
     */
    protected function openPaymentSession(Order $order): ?string
    {
        try {
            return app(StripePaymentService::class)->createSession($order);
        } catch (\Throwable $e) {
            Log::error('Errore riapertura sessione di pagamento asta', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
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

        // NB: la verifica dell'ordine esistente è dentro la transazione (vedi sotto),
        // non qui: farla prima del lock lasciava passare due submit ravvicinati fino
        // alla INSERT, che falliva sull'indice unico (auction_id, user_id).

        $this->normalizzaInput($request);
        $validated = $request->validate(self::REGOLE);

        $erroriItalia = $this->erroriSuiDatiItaliani($validated);

        if ($erroriItalia !== []) {
            return back()->withErrors($erroriItalia);
        }

        $lock = Cache::lock('auction_checkout_lock:'.auth()->id(), 30);

        if (! $lock->get()) {
            return back()->withErrors(['general' => __('messages.auction.already_processing')]);
        }

        try {
            $shippingZone = ShippingZone::findByCountry($validated['country']);

            if (! $shippingZone) {
                return back()->withErrors(['country' => __('messages.checkout.country_not_served')]);
            }

            $result = DB::transaction(fn () => $this->ordineDelVincitore($auction, $validated, $shippingZone));

            if ($result['paid']) {
                return redirect()->route('shop.auction-checkout.success', ['token' => $token]);
            }

            if (! $result['order']) {
                return back()->with('error', __('messages.checkout.error'));
            }

            // Pagamento forzato su Stripe
            $url = app(StripePaymentService::class)->createSession($result['order']);

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
     * Sanitizza input prima della validazione (allineato a StoreCheckoutRequest).
     */
    private function normalizzaInput(Request $request): void
    {
        if ($request->has('notes') && $request->notes !== null) {
            $request->merge(['notes' => strip_tags($request->notes)]);
        }

        if ($request->has('codice_fiscale') && $request->codice_fiscale !== null) {
            $request->merge(['codice_fiscale' => strtoupper(trim($request->codice_fiscale))]);
        }
    }

    /**
     * Per l'Italia il CAP e' di cinque cifre e il codice fiscale e'
     * obbligatorio: serve per la fattura.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function erroriSuiDatiItaliani(array $validated): array
    {
        if ($validated['country'] !== 'IT') {
            return [];
        }

        if (! preg_match('/^\d{5}$/', $validated['shipping_zip_code'])) {
            return ['shipping_zip_code' => __('validation.zip_code_it')];
        }

        if (empty($validated['codice_fiscale'])) {
            return ['codice_fiscale' => __('messages.auction.codice_fiscale_required')];
        }

        return [];
    }

    /**
     * Indirizzi di spedizione e fatturazione, nella forma salvata sull'ordine.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function indirizzi(array $validated): array
    {
        $shippingAddress = [
            'first_name' => $validated['shipping_first_name'],
            'last_name' => $validated['shipping_last_name'],
            'street' => $validated['shipping_street'],
            'city' => $validated['shipping_city'],
            'zip_code' => $validated['shipping_zip_code'],
            'province' => $validated['shipping_province'],
        ];

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

        return [$shippingAddress, $billingAddress];
    }

    /**
     * L'ordine del vincitore: quello gia' aperto se c'e', altrimenti nuovo.
     *
     * Gira dentro una transazione con lock sull'asta: serializza i submit
     * concorrenti sullo stesso token, cosi' il secondo trova l'ordine appena
     * creato invece di andare in errore sull'indice unico (auction_id,
     * user_id).
     *
     * @param  array<string, mixed>  $validated
     * @return array{order: Order|null, paid: bool}
     */
    private function ordineDelVincitore(Auction $auction, array $validated, ShippingZone $shippingZone): array
    {
        $lockedAuction = Auction::lockForUpdate()->find($auction->id);
        $existingOrder = $this->auctionService->getWinnerOrder($lockedAuction);

        if ($existingOrder && $existingOrder->paid_at !== null) {
            return ['order' => $existingOrder, 'paid' => true];
        }

        if ($existingOrder && $existingOrder->status !== OrderStatus::Pending) {
            // Annullato/rimborsato/in lavorazione: non ricreabile su questo token.
            return ['order' => null, 'paid' => false];
        }

        // L'importo dovuto è l'offerta del vincitore corrente, che può
        // non coincidere con current_bid in caso di riassegnazione.
        $winningBid = $this->auctionService->winningAmountFor($lockedAuction);
        $shippingCost = $shippingZone->calculateShippingCost($winningBid);
        [$shippingAddress, $billingAddress] = $this->indirizzi($validated);

        $dati = [
            'total_price' => round($winningBid + $shippingCost, 2),
            'shipping_address' => $shippingAddress,
            'billing_address' => $billingAddress,
            'country' => $validated['country'],
            'billing_country' => $validated['country'],
            'phone' => $validated['phone'],
            'codice_fiscale' => $validated['codice_fiscale'] ?? null,
            'shipping_cost' => $shippingCost,
            'notes' => $validated['notes'] ?? null,
        ];

        // Ordine già aperto e non pagato (checkout abbandonato su Stripe):
        // si riusa, aggiornando i dati appena inviati. Lo stock è già
        // riservato dal primo tentativo, non va riservato di nuovo.
        if ($existingOrder) {
            $existingOrder->update($dati);

            return ['order' => $existingOrder->fresh(), 'paid' => false];
        }

        // L'order_token lo genera il model (UUID casuale): non deve coincidere
        // con il token di checkout dell'asta, che circola via mail ed è un
        // segreto diverso.
        $order = new Order([
            ...$dati,
            'user_id' => auth()->id(),
            'payment_gateway' => PaymentGateway::Stripe,
            'privacy_accepted_at' => now(),
        ]);

        // status e auction_id non sono mass-assignable (sicurezza): vanno
        // valorizzati esplicitamente, e prima della INSERT perché è
        // l'indice unico (auction_id, user_id) a impedire il doppio ordine.
        $order->forceFill([
            'auction_id' => $lockedAuction->id,
            'status' => OrderStatus::Pending,
        ])->save();

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

        return ['order' => $order, 'paid' => false];
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

        $order = $this->auctionService->getWinnerOrder($auction)?->load(['items.product']);

        if (! $order) {
            abort(404);
        }

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

        $order = $this->auctionService->getWinnerOrder($auction);

        if (! $order) {
            abort(404);
        }

        // Il retry ha senso solo su un ordine ancora pagabile e con la finestra
        // di pagamento dell'asta ancora aperta.
        $canRetry = $order->status === OrderStatus::Pending
            && $order->paid_at === null
            && ! ($auction->winner_checkout_deadline?->isPast() ?? false);

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
