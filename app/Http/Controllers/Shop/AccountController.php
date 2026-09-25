<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\DatiDelCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'area "Il mio account" dello shop: dati, esportazione, cancellazione.
 *
 * Sta nel layout pubblico, accanto a "I miei ordini", perché è lì che un
 * cliente la cerca. La cancellazione di Breeze (`profile.destroy`) resta per
 * gli utenti del pannello, che non passano di qui.
 */
class AccountController extends Controller
{
    public function __construct(
        private readonly DatiDelCliente $dati,
    ) {}

    public function show(Request $request): Response
    {
        $utente = $request->user();

        return Inertia::render('Public/Shop/Account', [
            'account' => [
                'name' => $utente->name,
                'email' => $utente->email,
                'created_at' => $utente->created_at?->toIso8601String(),
                'orders_count' => $utente->orders()->count(),
            ],
            'motivoPerNonCancellare' => $this->dati->motivoPerNonCancellare($utente),
        ]);
    }

    /**
     * I dati in JSON, scaricati come file (art. 20 del GDPR: un formato
     * strutturato e leggibile da una macchina).
     */
    public function esporta(Request $request): JsonResponse
    {
        $nomeFile = 'savino-del-bene-volley-i-miei-dati-'.now()->format('Y-m-d').'.json';

        return response()
            ->json($this->dati->esporta($request->user()), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Disposition', 'attachment; filename="'.$nomeFile.'"')
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Cancella l'account.
     *
     * Chiede la password, come Breeze: un click da una sessione rimasta aperta
     * su un computer condiviso non deve bastare. Gli ordini restano — la legge
     * ne impone la conservazione per dieci anni — ma perdono il legame con
     * l'account (`orders.user_id` va a null); lo stesso per offerte e carrelli.
     * L'iscrizione alla newsletter è un consenso a parte e non si tocca: si
     * revoca dal link in fondo a ogni messaggio.
     */
    public function cancella(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $utente = $request->user();

        if ($motivo = $this->dati->motivoPerNonCancellare($utente)) {
            return back()->withErrors(['password' => $motivo]);
        }

        $id = $utente->id;

        Auth::logout();

        $this->dati->cancella($utente);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Nel log l'id e basta: l'email è proprio ciò che il cliente ha
        // chiesto di dimenticare.
        Log::channel('daily')->info('Account cliente cancellato su richiesta', ['user_id' => $id]);

        $lingua = app()->getLocale();
        $rotta = $lingua === config('app.fallback_locale', 'it') ? 'shop' : $lingua.'.shop';

        return redirect()->route($rotta)->with('success', __('messages.account.deleted'));
    }
}
