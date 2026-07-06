<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedPayment
{
    /**
     * Verifica che l'utente abbia un metodo di pagamento verificato.
     * Necessario per partecipare alle aste (piazzare offerte).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->has_verified_payment_method) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('messages.auction.payment_verification_required'),
                    'redirect' => route('account.payment-verification'),
                ], 403);
            }

            return redirect()->route('account.payment-verification')
                ->with('warning', __('messages.auction.payment_verification_required'));
        }

        return $next($request);
    }
}
