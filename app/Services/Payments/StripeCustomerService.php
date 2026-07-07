<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\Stripe;

class StripeCustomerService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Ottieni o crea un Stripe Customer per l'utente.
     */
    public function getOrCreateCustomer(User $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        $user->update(['stripe_customer_id' => $customer->id]);

        Log::info("Stripe Customer creato per User #{$user->id}: {$customer->id}");

        return $customer->id;
    }

    /**
     * Crea una Stripe Checkout Session in modalità 'setup' per verificare
     * un metodo di pagamento senza addebitare nulla.
     *
     * @return string URL di redirect alla pagina Stripe
     */
    public function createSetupSession(User $user): string
    {
        $customerId = $this->getOrCreateCustomer($user);

        $session = StripeSession::create([
            'mode' => 'setup',
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'success_url' => route('account.payment-verification.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('account.payment-verification'),
            'metadata' => [
                'user_id' => $user->id,
                'purpose' => 'auction_payment_verification',
            ],
        ]);

        Log::info("Setup Session Stripe creata per User #{$user->id}: {$session->id}");

        return $session->url;
    }

    /**
     * Gestisce la conferma del setup completato.
     * Chiamato dopo il redirect da Stripe o via webhook.
     */
    public function handleSetupComplete(User $user): void
    {
        $user->update([
            'has_verified_payment_method' => true,
            'payment_method_verified_at' => now(),
        ]);

        Log::info("Metodo di pagamento verificato per User #{$user->id}");
    }

    /**
     * Verifica una Stripe Checkout Session di setup e completa la verifica.
     */
    public function verifySetupSession(string $sessionId, User $user): bool
    {
        $session = StripeSession::retrieve($sessionId);

        if ($session->status !== 'complete') {
            return false;
        }

        if ((int) $session->metadata->user_id !== $user->id) {
            Log::warning("Setup session {$sessionId} non corrisponde a User #{$user->id}");

            return false;
        }

        $this->handleSetupComplete($user);

        return true;
    }
}
