<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Mail\AvvisoDiRecessoAlloShop;
use App\Mail\AvvisoDiRecessoAlTitolare;
use App\Mail\RicevutaDiRecesso;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RichiestaDiRecesso;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La funzione di recesso online (art. 54-bis del Codice del Consumo,
 * d.lgs. 209/2025, obbligatoria dal 19/06/2026).
 *
 * La norma chiede tre cose: che la funzione sia sempre raggiungibile (link nel
 * footer di ogni pagina e nel dettaglio dell'ordine), che il consumatore
 * inserisca nome, contratto e un recapito e poi confermi con un secondo
 * comando ("Conferma recesso": il passaggio di riepilogo sta nella pagina
 * Vue), e che riceva subito una ricevuta su supporto durevole con il contenuto
 * della dichiarazione, la data e l'ora.
 *
 * Non serve essere registrati ne' ricordare il numero giusto: la
 * dichiarazione si registra sempre, e l'aggancio all'ordine e' un aiuto per
 * chi la gestisce, non una condizione.
 *
 * Quando chi apre la pagina puo' vedere l'ordine — e' il suo account, oppure
 * arriva dal dettaglio dell'ordine o dall'email di conferma con il token
 * dell'ordine — sceglie gli articoli da una lista invece di descriverli a
 * testo. Le righe personalizzate (la firma della giocatrice) sono escluse dal
 * recesso (art. 59 c. 1 lett. c): la pagina non le lascia selezionare e
 * `store()` le rifiuta. Senza token ne' account la lista non si mostra: il
 * solo numero d'ordine non basta a leggere cosa ha comprato qualcun altro.
 */
class RecessoController extends Controller
{
    public function show(Request $request): Response
    {
        $utente = $request->user();
        $numero = trim((string) $request->query('ordine', ''));
        $token = (string) $request->query('token', '');
        $ordine = $this->ordineVisibile($request, $numero, $token);

        return Inertia::render('Public/Shop/Recesso', [
            'precompilato' => [
                'numero_ordine' => $numero,
                'nome' => (string) ($utente ? $utente->name : ''),
                'email' => (string) ($utente ? $utente->email : ''),
                'token' => $ordine ? $token : '',
            ],
            'righe' => $ordine ? $this->righeDellOrdine($ordine) : null,
            'ricevuta' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dati = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'numero_ordine' => ['required', 'string', 'max:50'],
            'articoli' => ['nullable', 'string', 'max:2000'],
            'righe' => ['nullable', 'array', 'max:100'],
            'righe.*' => ['integer'],
            'token' => ['nullable', 'string', 'max:100'],
            'conferma' => ['accepted'],
        ]);

        $numero = trim($dati['numero_ordine']);
        $articoli = filled($dati['articoli'] ?? null) ? trim($dati['articoli']) : null;

        // Gli articoli scelti dalla lista: si controllano prima di registrare,
        // perche' un rifiuto qui non e' una dichiarazione.
        if (array_key_exists('righe', $dati) && $dati['righe'] !== null) {
            $articoli = $this->articoliScelti($request, $numero, (string) ($dati['token'] ?? ''), $dati['righe'], $articoli);
        }

        $richiesta = RichiestaDiRecesso::create([
            'order_id' => Order::where('order_number', $numero)->value('id'),
            'numero_ordine' => $numero,
            'nome' => trim($dati['nome']),
            'email' => trim($dati['email']),
            'articoli' => $articoli,
            'lingua' => app()->getLocale(),
            'inviata_il' => now(),
        ]);

        // La ricevuta e' parte dell'obbligo, non una cortesia: parte subito,
        // senza passare dalla coda. Se l'invio fallisce la dichiarazione
        // resta valida e registrata, e la pagina mostra comunque la ricevuta.
        //
        // La dichiarazione si registra sempre; e' la sola email che ha un
        // tetto per destinatario (tre al giorno): il limite per IP della
        // rotta non ferma chi cambia indirizzo, e senza tetto il modulo
        // diventerebbe un modo per spedire posta a un indirizzo altrui. Oltre
        // la soglia la ricevuta resta a schermo, sull'indirizzo firmato.
        $chiave = 'recesso:'.strtolower($richiesta->email);
        if (! RateLimiter::tooManyAttempts($chiave, 3)) {
            RateLimiter::hit($chiave, 86400);

            try {
                Mail::to($richiesta->email, $richiesta->nome)->send(new RicevutaDiRecesso($richiesta));
            } catch (\Throwable $e) {
                Log::error('Ricevuta di recesso non inviata', ['richiesta' => $richiesta->id, 'errore' => $e->getMessage()]);
            }
        }

        // Chi conosce il numero di un ordine altrui può dichiarare il recesso a
        // nome di un altro: il titolare dell'ordine lo sa subito invece di
        // scoprirlo al rimborso. Riceve un avviso, non la ricevuta, senza i
        // dati di chi ha scritto; al massimo uno al giorno per ordine.
        if ($richiesta->emailDiversaDaQuellaDellOrdine()) {
            $chiaveTitolare = 'recesso-titolare:'.$richiesta->order_id;

            if (! RateLimiter::tooManyAttempts($chiaveTitolare, 1)) {
                RateLimiter::hit($chiaveTitolare, 86400);

                try {
                    Mail::to($richiesta->emailDellOrdine())->send(new AvvisoDiRecessoAlTitolare($richiesta));
                } catch (\Throwable $e) {
                    Log::error('Avviso di recesso al titolare non inviato', ['richiesta' => $richiesta->id, 'errore' => $e->getMessage()]);
                }
            }
        }

        $casella = SiteSetting::get('shop.support_email') ?: config('mail.from.address');

        try {
            Mail::to($casella)->queue(new AvvisoDiRecessoAlloShop($richiesta));
        } catch (\Throwable $e) {
            Log::error('Avviso di recesso allo shop non accodato', ['richiesta' => $richiesta->id, 'errore' => $e->getMessage()]);
        }

        // La ricevuta si mostra anche a schermo, subito, su un indirizzo suo:
        // ricaricare non rimanda la dichiarazione.
        $lingua = app()->getLocale();
        $rotta = $lingua === config('app.fallback_locale', 'it') ? 'recesso.ricevuta' : $lingua.'.recesso.ricevuta';

        return redirect()->to(URL::temporarySignedRoute($rotta, now()->addDays(90), ['richiesta' => $richiesta->id]));
    }

    public function ricevuta(RichiestaDiRecesso $richiesta): Response
    {
        return Inertia::render('Public/Shop/Recesso', [
            'precompilato' => ['numero_ordine' => '', 'nome' => '', 'email' => '', 'token' => ''],
            'righe' => null,
            'ricevuta' => [
                'numero_ordine' => $richiesta->numero_ordine,
                'nome' => $richiesta->nome,
                'email' => $richiesta->email,
                'articoli' => $richiesta->articoli,
                'inviata_il' => $richiesta->inviata_il->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
            ],
        ]);
    }

    /**
     * L'ordine, se chi chiede la pagina ha diritto di vederne le righe: e'
     * intestato al suo account, oppure conosce il token dell'ordine (che sta
     * solo nel dettaglio dell'ordine e nell'email di conferma).
     */
    private function ordineVisibile(Request $request, string $numero, string $token): ?Order
    {
        if ($numero === '') {
            return null;
        }

        $ordine = Order::where('order_number', $numero)->first();

        if (! $ordine) {
            return null;
        }

        $utente = $request->user();
        $suo = $utente !== null && $ordine->user_id !== null && (int) $ordine->user_id === (int) $utente->id;
        $conToken = $token !== '' && is_string($ordine->order_token) && hash_equals($ordine->order_token, $token);

        return $suo || $conToken ? $ordine : null;
    }

    /**
     * Le righe dell'ordine come le mostra la pagina: le personalizzate con il
     * segno, perche' non si possono scegliere.
     *
     * @return list<array{id: int, descrizione: string, personalizzata: bool}>
     */
    private function righeDellOrdine(Order $ordine): array
    {
        return $ordine->items()
            ->with(['product' => fn ($query) => $query->withTrashed(), 'variant'])
            ->orderBy('id')
            ->get()
            ->map(fn (OrderItem $riga) => [
                'id' => (int) $riga->id,
                'descrizione' => $this->descrizione($riga),
                'personalizzata' => $riga->personalizzazione !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * Il testo della dichiarazione per gli articoli scelti dalla lista. Chi
     * sceglie una riga personalizzata — la pagina non lo permette, quindi e'
     * una richiesta costruita a mano — riceve un errore invece di una
     * dichiarazione che la societa' dovrebbe poi respingere.
     *
     * @param  array<int, mixed>  $idRighe
     */
    private function articoliScelti(Request $request, string $numero, string $token, array $idRighe, ?string $nota): string
    {
        $ordine = $this->ordineVisibile($request, $numero, $token);
        $idRighe = array_values(array_unique(array_map('intval', $idRighe)));

        if (! $ordine) {
            throw ValidationException::withMessages(['righe' => __('messages.recesso.righe_non_valide')]);
        }

        if ($idRighe === []) {
            throw ValidationException::withMessages(['righe' => __('messages.recesso.nessuna_riga')]);
        }

        $righe = $ordine->items()
            ->with(['product' => fn ($query) => $query->withTrashed(), 'variant'])
            ->whereIn('id', $idRighe)
            ->orderBy('id')
            ->get();

        if ($righe->count() !== count($idRighe)) {
            throw ValidationException::withMessages(['righe' => __('messages.recesso.righe_non_valide')]);
        }

        if ($righe->contains(fn (OrderItem $riga) => $riga->personalizzazione !== null)) {
            throw ValidationException::withMessages(['righe' => __('messages.recesso.personalizzato_escluso')]);
        }

        $testo = $righe->map(fn (OrderItem $riga) => $this->descrizione($riga))->implode('; ');

        return mb_substr($nota !== null ? $testo."\n".$nota : $testo, 0, 2000);
    }

    private function descrizione(OrderItem $riga): string
    {
        $nome = $riga->product?->getTranslation('name', app()->getLocale()) ?: '#'.$riga->product_id;
        $variante = collect([$riga->variant?->size, $riga->variant?->color])->filter()->implode(' / ');
        $personalizzazione = $riga->personalizzazioneIn();

        return $riga->quantity.' × '.$nome
            .($variante !== '' ? ' ('.$variante.')' : '')
            .($personalizzazione ? ' + '.$personalizzazione : '');
    }
}
