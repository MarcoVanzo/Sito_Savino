<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripeCustomerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentVerificationController extends Controller
{
    public function __construct(
        private readonly StripeCustomerService $stripeCustomerService,
    ) {}

    /**
     * Mostra la pagina di verifica metodo di pagamento.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->has_verified_payment_method) {
            return redirect()->route('shop.auctions.index')
                ->with('success', __('messages.auction.payment_verified'));
        }

        return Inertia::render('Shop/PaymentVerification', [
            'hasVerifiedPayment' => false,
        ]);
    }

    /**
     * Crea una Stripe Setup Session e redirect a Stripe.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->has_verified_payment_method) {
            return redirect()->route('shop.auctions.index');
        }

        $url = $this->stripeCustomerService->createSetupSession($user);

        return Inertia::location($url);
    }

    /**
     * Gestisce il ritorno da Stripe dopo setup completato.
     */
    public function success(Request $request)
    {
        $user = $request->user();
        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return redirect()->route('account.payment-verification')
                ->with('error', __('messages.auction.payment_verification_failed'));
        }

        $verified = $this->stripeCustomerService->verifySetupSession($sessionId, $user);

        if (! $verified) {
            return redirect()->route('account.payment-verification')
                ->with('error', __('messages.auction.payment_verification_failed'));
        }

        return redirect()->route('shop.auctions.index')
            ->with('success', __('messages.auction.payment_verified'));
    }
}
