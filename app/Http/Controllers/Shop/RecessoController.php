<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Mail\AvvisoDiRecessoAlloShop;
use App\Mail\RicevutaDiRecesso;
use App\Models\Order;
use App\Models\RichiestaDiRecesso;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
 */
class RecessoController extends Controller
{
    public function show(Request $request): Response
    {
        $utente = $request->user();

        return Inertia::render('Public/Shop/Recesso', [
            'precompilato' => [
                'numero_ordine' => (string) $request->query('ordine', ''),
                'nome' => (string) ($utente ? $utente->name : ''),
                'email' => (string) ($utente ? $utente->email : ''),
            ],
            'ricevuta' => null,
        ]);
    }

    public function store(Request $request): Response
    {
        $dati = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'numero_ordine' => ['required', 'string', 'max:50'],
            'articoli' => ['nullable', 'string', 'max:2000'],
            'conferma' => ['accepted'],
        ]);

        $numero = trim($dati['numero_ordine']);

        $richiesta = RichiestaDiRecesso::create([
            'order_id' => Order::where('order_number', $numero)->value('id'),
            'numero_ordine' => $numero,
            'nome' => trim($dati['nome']),
            'email' => trim($dati['email']),
            'articoli' => filled($dati['articoli'] ?? null) ? trim($dati['articoli']) : null,
            'lingua' => app()->getLocale(),
            'inviata_il' => now(),
        ]);

        // La ricevuta e' parte dell'obbligo, non una cortesia: parte subito,
        // senza passare dalla coda. Se l'invio fallisce la dichiarazione
        // resta valida e registrata, e la pagina mostra comunque la ricevuta.
        try {
            Mail::to($richiesta->email, $richiesta->nome)->send(new RicevutaDiRecesso($richiesta));
        } catch (\Throwable $e) {
            Log::error('Ricevuta di recesso non inviata', ['richiesta' => $richiesta->id, 'errore' => $e->getMessage()]);
        }

        $casella = SiteSetting::get('shop.support_email') ?: config('mail.from.address');

        try {
            Mail::to($casella)->queue(new AvvisoDiRecessoAlloShop($richiesta));
        } catch (\Throwable $e) {
            Log::error('Avviso di recesso allo shop non accodato', ['richiesta' => $richiesta->id, 'errore' => $e->getMessage()]);
        }

        // La ricevuta si mostra anche a schermo, subito: la risposta alla POST
        // e' la pagina stessa, cosi' non passa dalla sessione.
        return Inertia::render('Public/Shop/Recesso', [
            'precompilato' => ['numero_ordine' => '', 'nome' => '', 'email' => ''],
            'ricevuta' => [
                'numero_ordine' => $richiesta->numero_ordine,
                'nome' => $richiesta->nome,
                'email' => $richiesta->email,
                'articoli' => $richiesta->articoli,
                'inviata_il' => $richiesta->inviata_il->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
            ],
        ]);
    }
}
