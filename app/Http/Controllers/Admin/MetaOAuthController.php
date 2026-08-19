<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\SocialAnalyticsPage;
use App\Http\Controllers\Controller;
use App\Services\Social\MetaException;
use App\Services\Social\MetaOAuthService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Il giro OAuth con Meta, che non può stare dentro una pagina Filament.
 *
 * Meta rimanda l'utente su un URL fisso con un `code` in query string: serve una
 * rotta HTTP normale, dichiarata identica nella configurazione dell'app Meta.
 * Da qui si torna sempre alla pagina Social Analytics con una notifica, in bene
 * o in male: chi ha cliccato "Collega" deve capire com'è finita senza leggere i log.
 */
class MetaOAuthController extends Controller
{
    public function __construct(private readonly MetaOAuthService $oauth) {}

    public function connect(Request $request): RedirectResponse
    {
        // La rotta è dietro `auth`: l'utente c'è, manca solo da verificare il ruolo.
        abort_unless($request->user()->role->canManageEditorial(), 403);

        if (! $this->oauth->isConfigured()) {
            return $this->back('App Meta non configurata: mancano META_APP_ID e META_APP_SECRET.', success: false);
        }

        return redirect()->away($this->oauth->authorizationUrl($request->user()->id));
    }

    public function callback(Request $request): RedirectResponse
    {
        // Lo state va verificato prima di qualunque altra cosa: è l'unica difesa
        // contro una callback costruita da terzi.
        $state = (string) $request->query('state', '');
        $context = $state === '' ? null : $this->oauth->consumeState($state);

        if ($context === null) {
            return $this->back('Richiesta di collegamento non valida o scaduta: riprova.', success: false);
        }

        if (filled($request->query('error'))) {
            // L'utente ha annullato o negato i permessi: non è un guasto.
            return $this->back('Collegamento annullato su Meta.', success: false);
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return $this->back('Meta non ha restituito il codice di autorizzazione.', success: false);
        }

        try {
            $accounts = $this->oauth->connect($code, $context['user_id']);
        } catch (MetaException $e) {
            Log::warning('Meta: collegamento fallito', ['reason' => $e->reason, 'message' => $e->getMessage()]);

            return $this->back($e->userMessage(), success: false);
        }

        $withInstagram = $accounts->filter->hasInstagram()->count();

        $message = trans_choice(
            '{1}Collegato 1 account Meta.|[2,*]Collegati :count account Meta.',
            $accounts->count(),
            ['count' => $accounts->count()],
        );

        if ($withInstagram < $accounts->count()) {
            // Senza Instagram la pagina mostra solo la Pagina Facebook: meglio
            // dirlo subito che farlo scoprire da una sezione vuota.
            $message .= ' Attenzione: '.($accounts->count() - $withInstagram)
                .' senza profilo Instagram collegato alla Pagina.';
        }

        return $this->back($message);
    }

    private function back(string $message, bool $success = true): RedirectResponse
    {
        $notification = Notification::make()->title($message);
        $success ? $notification->success() : $notification->danger();
        $notification->send();

        return redirect()->to(SocialAnalyticsPage::getUrl());
    }
}
