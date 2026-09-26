<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Enums\PaymentGateway;
use App\Models\ActivityLog;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RichiestaDiRecesso;
use App\Models\User;
use App\Services\Payments\StripeCustomerService;
use App\Support\CondizioniDiVendita;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Quello che il sito sa di un cliente dello shop, per l'esportazione e per la
 * cancellazione dell'account (articoli 15, 17 e 20 del GDPR).
 *
 * Fino al 25 settembre 2026 il cliente non trovava come cancellarsi e non
 * poteva scaricare niente: la pagina di Breeze con la cancellazione stava sotto
 * `/profile`, nel layout del pannello, e nessun link ci portava.
 */
class DatiDelCliente
{
    /**
     * I dati in un formato leggibile da una macchina e da una persona.
     *
     * Ci sono i dati dell'account, gli ordini con indirizzi e articoli, le
     * offerte alle aste e, con lo stesso indirizzo, le dichiarazioni di
     * recesso, i messaggi dei moduli e l'iscrizione alla newsletter. Non ci sono l'impronta della password né lo storico delle
     * impronte: non sono dati del cliente, sono il modo in cui lo proteggiamo.
     *
     * @return array<string, mixed>
     */
    public function esporta(User $utente): array
    {
        // Riletto dal database: l'istanza della sessione può non avere tutte
        // le colonne, e il modello rifiuta di leggere quelle che mancano.
        $utente = $utente->fresh() ?? $utente;

        // Anche gli ordini fatti da ospite con lo stesso indirizzo, ma solo se
        // l'indirizzo è verificato: altrimenti chiunque registrasse un
        // account con l'email di un altro ne scaricherebbe indirizzi,
        // telefono e codice fiscale.
        $ordini = Order::query()
            ->where(fn ($q) => $q
                ->where('user_id', $utente->id)
                ->when($utente->email_verified_at !== null, fn ($q) => $q
                    ->orWhere(fn ($q) => $q->whereNull('user_id')->where('guest_email', $utente->email))))
            ->with(['items.product', 'items.variant', 'coupon'])
            ->orderBy('created_at')
            ->get();

        $iscrizione = NewsletterSubscriber::where('email', $utente->email)->first();

        return [
            'esportato_il' => now()->toIso8601String(),
            'titolare' => CondizioniDiVendita::RAGIONE_SOCIALE,
            'account' => [
                'nome' => $utente->name,
                'email' => $utente->email,
                'email_verificata_il' => $this->data($utente->email_verified_at),
                'lingua' => $utente->locale ?? null,
                'indirizzo' => $utente->address,
                'creato_il' => $this->data($utente->created_at),
                'metodo_di_pagamento_verificato_per_le_aste' => (bool) $utente->has_verified_payment_method,
            ],
            'ordini' => $ordini->map(fn (Order $ordine) => [
                'numero' => $ordine->order_number,
                'data' => $this->data($ordine->created_at),
                'stato' => $ordine->status->value,
                'totale' => (float) $ordine->total_price,
                'spedizione' => (float) $ordine->shipping_cost,
                'sconto' => (float) $ordine->coupon_discount,
                'coupon' => $ordine->coupon?->code,
                'metodo_di_pagamento' => $ordine->payment_gateway?->value,
                'indirizzo_di_spedizione' => $ordine->shipping_address,
                'indirizzo_di_fatturazione' => $ordine->billing_address,
                'paese' => $ordine->country,
                'telefono' => $ordine->phone,
                'codice_fiscale' => $ordine->codice_fiscale,
                'note' => $ordine->notes,
                'condizioni_accettate' => $ordine->condizioni_versione,
                'impronta_delle_condizioni' => $ordine->condizioni_impronta,
                'articoli' => $ordine->items->map(fn (OrderItem $articolo) => [
                    'prodotto' => $articolo->product?->name,
                    'variante' => $articolo->variant
                        ? collect([$articolo->variant->size, $articolo->variant->color])->filter()->implode(' / ')
                        : null,
                    'quantita' => $articolo->quantity,
                    'prezzo' => (float) $articolo->price_at_time_of_purchase,
                ])->values()->all(),
            ])->values()->all(),
            // Il carrello aperto: poca cosa, ma è legato all'account.
            'carrello' => Cart::where('user_id', $utente->id)
                ->with(['items.product', 'items.variant'])
                ->get()
                ->flatMap(fn (Cart $carrello) => $carrello->items)
                ->map(fn (CartItem $articolo) => [
                    'prodotto' => $articolo->product?->name,
                    'variante' => $articolo->variant
                        ? collect([$articolo->variant->size, $articolo->variant->color])->filter()->implode(' / ')
                        : null,
                    'quantita' => $articolo->quantity,
                    'con_personalizzazione' => (bool) $articolo->con_personalizzazione,
                    'aggiunto_il' => $this->data($articolo->created_at),
                ])->values()->all(),
            'offerte_alle_aste' => $utente->bids()
                ->with('auction')
                ->orderBy('placed_at')
                ->get()
                ->map(fn (Bid $offerta) => [
                    'asta' => $offerta->auction?->title,
                    'importo' => (float) $offerta->amount,
                    'data' => $this->data($offerta->placed_at),
                    'valida' => (bool) $offerta->is_valid,
                ])->values()->all(),
            // Stesso indirizzo dell'account: sono dati suoi anche se li ha
            // lasciati senza essere entrato (modulo di recesso, contatti,
            // accrediti stampa).
            'dichiarazioni_di_recesso' => RichiestaDiRecesso::where('email', $utente->email)
                ->orderBy('inviata_il')
                ->get()
                ->map(fn (RichiestaDiRecesso $richiesta) => [
                    'ordine' => $richiesta->numero_ordine,
                    'nome' => $richiesta->nome,
                    'articoli' => $richiesta->articoli,
                    'inviata_il' => $this->data($richiesta->inviata_il),
                    'gestita_il' => $this->data($richiesta->gestita_il),
                ])->values()->all(),
            'messaggi' => ContactMessage::where('email', $utente->email)
                ->orderBy('created_at')
                ->get()
                ->map(fn (ContactMessage $messaggio) => [
                    'data' => $this->data($messaggio->created_at),
                    'nome' => $messaggio->name,
                    'oggetto' => $messaggio->subject,
                    'testo' => $messaggio->message,
                ])->values()->all(),
            'newsletter' => $iscrizione ? [
                'email' => $iscrizione->email,
                'nome' => $iscrizione->first_name,
                'iscritto_il' => $this->data($iscrizione->subscribed_at),
                'confermato_il' => $this->data($iscrizione->confermato_il),
                'disiscritto_il' => $this->data($iscrizione->unsubscribed_at),
            ] : null,
        ];
    }

    /**
     * Perché l'account non si può cancellare adesso, o null se si può.
     *
     * Due casi soli. Un account della redazione si gestisce dal pannello: da
     * qui si cancellerebbe chi scrive le notizie. E chi partecipa a un'asta
     * ancora aperta, o chiusa ma non pagata (vincitore o offerenti che
     * possono subentrargli), ha un impegno in corso: cancellando l'account
     * l'offerta resterebbe senza nessuno dietro.
     */
    public function motivoPerNonCancellare(User $utente): ?string
    {
        if ($utente->role->canAccessPanel()) {
            return __('messages.account.delete_blocked_staff');
        }

        // Il turno di pagamento resta aperto finche' il giro orario non lo
        // chiude: una scadenza appena passata non libera ancora il vincitore.
        $astaDaPagare = Auction::where('winner_user_id', $utente->id)
            ->where('status', AuctionStatus::Ended)
            ->whereNotNull('winner_checkout_deadline')
            ->whereDoesntHave('orders', fn ($q) => $q->whereNotNull('paid_at'))
            ->exists();

        // Non basta il vincitore: su un'asta chiusa e non ancora pagata chi e'
        // arrivato secondo o terzo puo' ancora ricevere l'asta se il primo non
        // paga.
        $astaInCorso = $utente->bids()
            ->where('is_valid', true)
            ->whereHas('auction', fn ($q) => $q
                ->where('status', AuctionStatus::Active)
                ->orWhere(fn ($q) => $q
                    ->where('status', AuctionStatus::Ended)
                    ->whereNotNull('winner_checkout_deadline')
                    ->whereDoesntHave('orders', fn ($q) => $q->whereNotNull('paid_at'))))
            ->exists();

        if ($astaDaPagare || $astaInCorso) {
            return __('messages.account.delete_blocked_auction');
        }

        return null;
    }

    /**
     * Cancella l'account e ciò che il registro delle attività ne ricorda.
     *
     * Il modello `User` passa da `LogsActivity`, che alla creazione e a ogni
     * modifica copia nome, email, telefono e indirizzo in
     * `activity_logs.changes`, e il nome in `model_label`: senza questo passo
     * quei dati sopravvivrebbero alla cancellazione per i 180 giorni del
     * registro. Le righe restano (dicono che un account è esistito ed è stato
     * cancellato), senza dati personali; lo stesso per IP e browser delle
     * azioni fatte dal cliente, che dopo la cancellazione non si
     * ritroverebbero più (`user_id` va a null).
     *
     * Gli ordini restano (conservazione fiscale, `user_id` a null), ma prima
     * ricevono nome ed email dell'account dove non li hanno: l'ordine d'asta
     * non li valorizza mai, e quello dello shop fatto da cliente registrato
     * può non avere il nome. Senza, un ordine pagato e non ancora spedito
     * resterebbe senza nessuno a cui mandare spedizione, rimborso o la
     * ricevuta di un recesso (`RichiestaDiRecesso::emailDellOrdine`). È
     * esecuzione del contratto e obbligo fiscale, non un nuovo trattamento:
     * l'informativa dice che alla cancellazione restano i documenti fiscali,
     * e l'ordine con i suoi recapiti è uno di quelli.
     */
    public function cancella(User $utente): void
    {
        // Riletto dal database, come in esporta(): l'istanza della sessione
        // può non avere tutte le colonne (stripe_customer_id).
        $utente = $utente->fresh() ?? $utente;

        $this->cancellaIlClienteSuStripe($utente);

        DB::transaction(function () use ($utente) {
            $id = $utente->id;

            Order::where('user_id', $id)
                ->where(fn ($q) => $q->whereNull('guest_email')->orWhere('guest_email', ''))
                ->update(['guest_email' => $utente->email]);
            Order::where('user_id', $id)
                ->where(fn ($q) => $q->whereNull('guest_name')->orWhere('guest_name', ''))
                ->update(['guest_name' => $utente->name]);

            ActivityLog::where('user_id', $id)->update(['ip_address' => null, 'user_agent' => null]);

            $utente->delete();

            ActivityLog::where('model_type', User::class)
                ->where('model_id', $id)
                ->update(['changes' => null, 'model_label' => 'Cliente #'.$id]);
        });
    }

    /**
     * Il cliente Stripe nato per la verifica della carta delle aste
     * (`StripeCustomerService::getOrCreateCustomer`) porta nome ed email: si
     * cancella con l'account. Un errore di Stripe non blocca la
     * cancellazione, che è un diritto del cliente: si registra, senza dati
     * personali, e si rimedia a mano dal pannello di Stripe.
     */
    private function cancellaIlClienteSuStripe(User $utente): void
    {
        $idCliente = $utente->stripe_customer_id;

        if (! $idCliente || ! PaymentGateway::Stripe->configurato()) {
            return;
        }

        try {
            app(StripeCustomerService::class)->cancellaCustomer($idCliente);
        } catch (\Throwable $e) {
            Log::warning('Stripe: cliente non cancellato insieme all\'account', [
                'user_id' => $utente->id,
                'errore' => $e::class,
            ]);
        }
    }

    /**
     * Le date in ISO 8601, che è ciò che un altro programma sa leggere. Le
     * colonne arrivano come Carbon dai cast, ma alcune (quelle aggiunte dopo,
     * o lette da una relazione) possono essere ancora stringhe.
     */
    private function data(mixed $valore): ?string
    {
        return $valore ? Carbon::parse($valore)->toIso8601String() : null;
    }
}
