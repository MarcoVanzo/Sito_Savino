<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\DatiDelVincitoreDellAsta;
use App\Models\Auction;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Models\SiteSetting;
use App\Services\AuctionService;
use App\Services\OrdineDelVincitoreDellAsta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuctionCheckoutController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
        protected OrdineDelVincitoreDellAsta $ordineDelVincitore,
    ) {}

    /**
     * Mostra la pagina di checkout per il vincitore dell'asta.
     */
    public function show(string $token): Response|RedirectResponse|SymfonyResponse
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

        // Un addebito ancora in corso (SEPA, cattura PayPal in verifica) vale
        // come pagato: riproporre il modulo farebbe pagare due volte.
        if ($existingOrder && ($existingOrder->paid_at !== null || $existingOrder->haUnPagamentoInSospeso())) {
            return redirect()->route('shop.auction-checkout.success', ['token' => $token])
                ->with('info', __('messages.auction_checkout.already_ordered'));
        }

        // Ordine ancora da pagare e deadline aperta: riapri una sessione di
        // pagamento sull'ordine esistente (stesso order_token), senza crearne
        // uno nuovo né duplicare la riserva di stock.
        // Si riapre da sola solo quando non c'è niente da scegliere: con più
        // metodi offerti chi è tornato indietro dal gateway deve poter
        // cambiare (da PayPal alla carta), e il modulo glielo ripropone già
        // compilato con i dati del primo tentativo.
        if ($existingOrder && $existingOrder->status === OrderStatus::Pending) {
            // Null se il metodo scelto la prima volta non è più offerto: il
            // vincitore rivede il modulo e ne sceglie un altro.
            $retryUrl = count(PaymentGateway::offertiAlleAste()) === 1
                ? $this->ordineDelVincitore->riapriIlPagamento($existingOrder)
                : null;

            if ($retryUrl) {
                return Inertia::location($retryUrl);
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

        // Il costo di ogni zona lo calcola il server, con lo stesso conto che
        // addebita l'ordine (store()): offerta e peso del collo sono fissi, e
        // al client resta solo la scelta della zona. Quando il conto era
        // ripetuto nel client, il vincitore vedeva la tariffa base mentre
        // l'ordine applicava le fasce di peso.
        $winningBid = $this->auctionService->winningAmountFor($auction);
        $peso = $auction->product?->pesoPerLaSpedizione() ?? 0.0;

        $shippingZones = ShippingZone::active()->ordered()->get()
            ->map(fn (ShippingZone $zone): array => [
                ...$zone->toArray(),
                // La soglia che vale davvero (globale o della zona), non
                // quella grezza della zona.
                'free_threshold' => $zone->sogliaGratuita(),
                'costo_spedizione' => $zone->calculateShippingCost($winningBid, $peso),
            ])
            ->all();

        return Inertia::render('Public/Shop/Auctions/Checkout', [
            'auction' => $auction,
            'product' => $auction->product,
            'shippingZones' => $shippingZones,
            'checkoutDeadline' => $auction->winner_checkout_deadline?->toIso8601String(),
            'winningBid' => $winningBid,
            // Solo i metodi con le credenziali e attivi dal pannello, gli
            // stessi del checkout dello shop (PaymentGateway::offertiAlleAste).
            'paymentGateways' => array_map(fn (PaymentGateway $g): array => [
                'value' => $g->value,
                'label' => $g->getLabel(),
                'icon' => $g->getIcon(),
            ], PaymentGateway::offertiAlleAste()),
            'giorniBonifico' => PaymentGateway::giorniPerIlBonifico(),
            'datiGiaInseriti' => $existingOrder?->status === OrderStatus::Pending
                ? $this->datiDelPrimoTentativo($existingOrder)
                : null,
        ]);
    }

    /**
     * I campi del modulo come li ha compilati il vincitore la prima volta.
     *
     * @return array<string, mixed>
     */
    private function datiDelPrimoTentativo(Order $order): array
    {
        $spedizione = (array) ($order->shipping_address ?? []);
        $fatturazione = (array) ($order->billing_address ?? []);
        $stessoIndirizzo = $fatturazione === [] || $fatturazione == $spedizione;

        $dati = [
            'country' => $order->country,
            'phone' => $order->phone,
            'codice_fiscale' => $order->codice_fiscale,
            'payment_gateway' => $order->payment_gateway?->value,
            'billing_same_as_shipping' => $stessoIndirizzo,
        ];

        foreach (['first_name', 'last_name', 'street', 'city', 'zip_code', 'province'] as $campo) {
            $dati["shipping_{$campo}"] = $spedizione[$campo] ?? null;

            if (! $stessoIndirizzo) {
                $dati["billing_{$campo}"] = $fatturazione[$campo] ?? null;
            }
        }

        return array_filter($dati, fn ($valore) => $valore !== null && $valore !== '');
    }

    /**
     * Processa il checkout dell'asta e avvia il pagamento sul gateway scelto.
     */
    public function store(DatiDelVincitoreDellAsta $request, string $token): RedirectResponse|SymfonyResponse
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

        // NB: la verifica dell'ordine esistente è dentro la transazione di
        // OrdineDelVincitoreDellAsta::registra,
        // non qui: farla prima del lock lasciava passare due submit ravvicinati fino
        // alla INSERT, che falliva sull'indice unico (auction_id, user_id).

        $validated = $request->validated();

        $lock = Cache::lock('auction_checkout_lock:'.auth()->id(), 30);

        if (! $lock->get()) {
            return back()->withErrors(['general' => __('messages.auction.already_processing')]);
        }

        try {
            $shippingZone = ShippingZone::findByCountry($validated['country']);

            if (! $shippingZone) {
                return back()->withErrors(['country' => __('messages.checkout.country_not_served')]);
            }

            $result = $this->ordineDelVincitore->registra($auction, $validated, $shippingZone, (int) auth()->id());

            if ($result['paid']) {
                return redirect()->route('shop.auction-checkout.success', ['token' => $token]);
            }

            if (! $result['order']) {
                return back()->with('error', __('messages.checkout.error'));
            }

            if ($result['order']->payment_gateway === PaymentGateway::BankTransfer) {
                return $this->confermaDelBonifico($result['order'], $token, $result['bonificoNuovo']);
            }

            // Inertia::location e non redirect()->away(): il form di checkout
            // è Inertia e un 302 verso stripe.com o paypal.com verrebbe
            // seguito dalla XHR, che muore sul CORS del gateway con l'ordine
            // già creato e la merce riservata. Fuori da Inertia degrada da sé
            // al 302.
            return Inertia::location($this->ordineDelVincitore->urlDelGateway($result['order']));
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
     * Il vincitore ha scelto il bonifico: nessun gateway da raggiungere.
     * Email e avvisi partono solo alla prima scelta del bonifico
     * (OrdineDelVincitoreDellAsta::avvisaDelBonifico).
     */
    private function confermaDelBonifico(Order $order, string $token, bool $nuovo): RedirectResponse
    {
        if ($nuovo) {
            $this->ordineDelVincitore->avvisaDelBonifico($order);
        }

        return redirect()->route('shop.auction-checkout.success', ['token' => $token])
            ->with('success', __('messages.checkout.success_bank'));
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
            'bonifico' => $this->istruzioniDelBonifico($order, $auction),
        ]);
    }

    /**
     * Le coordinate da mostrare al vincitore che paga con bonifico, le stesse
     * dell'email di conferma. Null per gli altri metodi e a pagamento
     * registrato.
     *
     * @return array{iban: string, intestatario: string, causale: string, entro: string|null}|null
     */
    private function istruzioniDelBonifico(Order $order, Auction $auction): ?array
    {
        if ($order->payment_gateway !== PaymentGateway::BankTransfer
            || $order->paid_at !== null
            || $order->status !== OrderStatus::Pending) {
            return null;
        }

        return [
            'iban' => (string) SiteSetting::get('shop.bank_transfer_iban', ''),
            'intestatario' => (string) SiteSetting::get('shop.bank_transfer_beneficiary', ''),
            'causale' => __('emails.confirmation.bank_reason_value', ['number' => $order->order_number]),
            'entro' => $auction->winner_checkout_deadline?->toIso8601String(),
        ];
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
