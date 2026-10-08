<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Mail\OrderConfirmation;
use App\Models\Auction;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Services\Payments\PayPalPaymentService;
use App\Services\Payments\StripePaymentService;
use App\Support\CondizioniDiVendita;
use App\Support\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * L'ordine del vincitore di un'asta: scrittura, termine del bonifico,
 * sessione di pagamento sul gateway e avvisi del bonifico.
 *
 * Controlli d'accesso, lock per utente, redirect e Inertia::location verso il
 * gateway restano in AuctionCheckoutController.
 */
class OrdineDelVincitoreDellAsta
{
    public function __construct(
        protected AuctionService $auctionService,
    ) {}

    /**
     * L'ordine del vincitore: quello gia' aperto se c'e', altrimenti nuovo.
     *
     * Gira dentro una transazione con lock sull'asta: serializza i submit
     * concorrenti sullo stesso token, cosi' il secondo trova l'ordine appena
     * creato invece di andare in errore sull'indice unico (auction_id,
     * user_id).
     *
     * `bonificoNuovo` dice se il bonifico è stato scelto adesso: al reinvio
     * dello stesso modulo email e avviso non ripartono.
     *
     * @param  array<string, mixed>  $validated
     * @return array{order: Order|null, paid: bool, bonificoNuovo: bool}
     */
    public function registra(Auction $auction, array $validated, ShippingZone $shippingZone, int $userId): array
    {
        return DB::transaction(fn (): array => $this->registraNelLock($auction, $validated, $shippingZone, $userId));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{order: Order|null, paid: bool, bonificoNuovo: bool}
     */
    private function registraNelLock(Auction $auction, array $validated, ShippingZone $shippingZone, int $userId): array
    {
        $lockedAuction = Auction::lockForUpdate()->find($auction->id);
        $existingOrder = $this->auctionService->getWinnerOrder($lockedAuction);

        if ($existingOrder && ($existingOrder->paid_at !== null || $existingOrder->haUnPagamentoInSospeso())) {
            return ['order' => $existingOrder, 'paid' => true, 'bonificoNuovo' => false];
        }

        if ($existingOrder && $existingOrder->status !== OrderStatus::Pending) {
            // Annullato/rimborsato/in lavorazione: non ricreabile su questo token.
            return ['order' => null, 'paid' => false, 'bonificoNuovo' => false];
        }

        $eraGiaBonifico = $existingOrder?->payment_gateway === PaymentGateway::BankTransfer;

        // L'importo dovuto è l'offerta del vincitore corrente, che può
        // non coincidere con current_bid in caso di riassegnazione.
        $winningBid = $this->auctionService->winningAmountFor($lockedAuction);
        // Un pezzo solo: il peso e' quello del prodotto battuto, ripiego
        // compreso (Product::pesoPerLaSpedizione), come per il carrello.
        $shippingCost = $shippingZone->calculateShippingCost(
            $winningBid,
            $lockedAuction->product?->pesoPerLaSpedizione() ?? 0.0,
        );
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
            // Si può cambiare metodo fra un tentativo e l'altro: chi è tornato
            // indietro da PayPal può scegliere la carta, e viceversa.
            'payment_gateway' => PaymentGateway::from($validated['payment_gateway']),
        ];

        // Ordine già aperto e non pagato (checkout abbandonato sul gateway):
        // si riusa, aggiornando i dati appena inviati. Lo stock è già
        // riservato dal primo tentativo, non va riservato di nuovo.
        if ($existingOrder) {
            $existingOrder->update([
                ...$dati,
                'notes' => self::noteConLaNotaDelCliente($existingOrder->notes, $dati['notes']),
            ]);
            $this->terminePerIlBonifico($lockedAuction, $existingOrder);

            return ['order' => $existingOrder->fresh(), 'paid' => false, 'bonificoNuovo' => ! $eraGiaBonifico];
        }

        // L'order_token lo genera il model (UUID casuale): non deve coincidere
        // con il token di checkout dell'asta, che circola via mail ed è un
        // segreto diverso.
        $order = new Order([
            ...$dati,
            'user_id' => $userId,
            'privacy_accepted_at' => now(),
            'condizioni_versione' => CondizioniDiVendita::VERSIONE,
            'condizioni_impronta' => CondizioniDiVendita::registraIstantanea(Locale::current()),
        ]);

        // status e auction_id non sono mass-assignable (sicurezza): vanno
        // valorizzati esplicitamente, e prima della INSERT perché è
        // l'indice unico (auction_id, user_id) a impedire il doppio ordine.
        $order->forceFill([
            'auction_id' => $lockedAuction->id,
            'status' => OrderStatus::Pending,
        ])->save();

        // L'unico articolo dell'asta, con la sua riserva di stock: stessa
        // strada che percorre il carrello dello shop.
        $order->registraArticolo(
            $auction->product_id,
            null,
            1,
            $winningBid,
            "asta #{$auction->id}",
        );

        $this->terminePerIlBonifico($lockedAuction, $order);

        return ['order' => $order, 'paid' => false, 'bonificoNuovo' => true];
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
     * Le note dell'ordine con la nota del cliente aggiornata.
     *
     * `orders.notes` tiene insieme la nota scritta nel modulo e le annotazioni
     * di revisione (HandlesPaymentWebhooks::flagForManualReview), accodate su
     * righe che cominciano con «[gg/mm/aaaa hh:mm REVISIONE MANUALE]». Al
     * reinvio del modulo si sostituisce solo la parte che le precede: prima
     * si riscriveva tutto e le annotazioni sparivano, compresi gli
     * identificativi su cui webhook e contestazioni evitano i doppioni.
     */
    private static function noteConLaNotaDelCliente(?string $attuali, ?string $notaDelCliente): ?string
    {
        $annotazioni = '';

        if ($attuali !== null && preg_match('/^\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} REVISIONE MANUALE\]/m', $attuali, $trovato, PREG_OFFSET_CAPTURE)) {
            $annotazioni = substr($attuali, $trovato[0][1]);
        }

        $note = trim(trim((string) $notaDelCliente)."\n".$annotazioni);

        return $note === '' ? null : $note;
    }

    /**
     * Con il bonifico il termine del vincitore diventa quello del bonifico.
     *
     * Le 48 ore dell'asta non bastano a un accredito: senza spostarle,
     * `AuctionService::checkWinnerPayments` annullerebbe l'ordine e passerebbe
     * il lotto al secondo offerente mentre i soldi sono in viaggio. Il termine
     * si conta dalla creazione dell'ordine, come nello shop e come dice
     * l'email di conferma, e non si allunga a ogni nuovo invio del modulo:
     * rimandare il bonifico non deve tenere fermo il lotto all'infinito.
     * Non si accorcia mai: chi sceglie il bonifico nei primi minuti tiene
     * comunque le sue 48 ore.
     */
    private function terminePerIlBonifico(Auction $auction, Order $order): void
    {
        if ($order->payment_gateway !== PaymentGateway::BankTransfer) {
            return;
        }

        $termine = $order->created_at->copy()->addDays(PaymentGateway::giorniPerIlBonifico());

        if ($auction->winner_checkout_deadline === null || $termine->gt($auction->winner_checkout_deadline)) {
            $auction->forceFill(['winner_checkout_deadline' => $termine])->save();
        }
    }

    /**
     * Apre una nuova sessione di pagamento su un ordine già esistente e non
     * pagato, con il metodo scelto la prima volta.
     *
     * Restituisce l'URL del gateway, oppure null quando la sessione non si
     * può aprire: il metodo non è più offerto, sull'ordine c'è già una
     * transazione (un incasso da rivedere non si ripaga), o il gateway non
     * risponde. In quei casi il chiamante mostra di nuovo il modulo.
     */
    public function riapriIlPagamento(Order $order): ?string
    {
        // Il bonifico non ha una sessione da riaprire: il vincitore rivede il
        // modulo, dove può confermarlo o passare a PayPal o alla carta.
        if ($order->payment_id !== null
            || $order->payment_gateway === PaymentGateway::BankTransfer
            || ! in_array($order->payment_gateway, PaymentGateway::offertiAlleAste(), true)) {
            return null;
        }

        try {
            return $this->urlDelGateway($order);
        } catch (\Throwable $e) {
            Log::error('Errore riapertura sessione di pagamento asta', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * La sessione di pagamento sul gateway dell'ordine.
     *
     * Le due strade sono quelle del checkout dello shop: Stripe incassa da sé
     * prima di rimandare indietro, PayPal incassa al ritorno sulla pagina di
     * conferma (Order::successUrl) e nel webhook, idempotenti fra loro.
     */
    public function urlDelGateway(Order $order): string
    {
        return match ($order->payment_gateway) {
            PaymentGateway::Stripe => app(StripePaymentService::class)->createSession($order),
            PaymentGateway::PayPal => app(PayPalPaymentService::class)->createSession($order),
            default => throw new \LogicException("Metodo di pagamento non ammesso per le aste: {$order->payment_gateway?->value}"),
        };
    }

    /**
     * Il vincitore ha scelto il bonifico: nessun gateway da raggiungere.
     *
     * Come nello shop (CheckoutController::handleBankTransfer) partono
     * l'email di conferma, che porta IBAN, intestatario, causale e termine,
     * e l'avviso alla redazione, che conferma l'accredito dal pannello
     * ("Conferma Pagamento" sull'ordine).
     *
     * L'ordine a questo punto esiste già e il termine è spostato: un guasto
     * nell'accodare l'email non deve mandare il vincitore sulla pagina
     * d'errore, che gli farebbe credere di dover rifare tutto. Le coordinate
     * le trova comunque sulla pagina di conferma.
     */
    public function avvisaDelBonifico(Order $order): void
    {
        try {
            $order->loadMissing('user');
            $email = $order->user->email ?? $order->guest_email;

            if ($email) {
                Mail::to($email)->queue(new OrderConfirmation($order));
            }

            app(AdminNotificationService::class)->notifyNewOrder($order);
            app(AvvisoNuovoOrdine::class)->invia($order);
        } catch (\Throwable $e) {
            Log::error('Bonifico d\'asta: email o avviso non partiti', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
