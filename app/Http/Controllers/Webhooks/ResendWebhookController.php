<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\EsitoAvviso;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AvvisoTecnico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Le email che Resend non è riuscito a consegnare.
 *
 * Resend che accetta una email non vuol dire che il cliente l'abbia ricevuta:
 * un indirizzo sbagliato al checkout, una casella piena o un filtro che la
 * rifiuta si scoprono solo dopo, e il cliente che ha pagato resta senza
 * conferma d'ordine senza che nessuno lo sappia. Qui arrivano quei casi, e
 * diventano un avviso in `allarmi@`.
 *
 * **Solo per i clienti.** L'avviso parte se il destinatario ha un ordine negli
 * ultimi ORDINI_RECENTI_GIORNI giorni (email dell'ospite o dell'account): è il
 * caso in cui qualcuno va contattato. Gli altri indirizzi li scrivono terzi —
 * iscrizione alla newsletter, ricevuta del recesso, modulo contatti — e chi ne
 * inserisce cento inesistenti farebbe cento email ad `allarmi@`: finiscono nel
 * log, senza l'indirizzo (nei log l'id, mai l'email). In più un tetto di
 * AVVISI_ALL_ORA avvisi l'ora, per tutti.
 *
 * La firma è quella di Svix, che Resend usa per i webhook: HMAC-SHA256 su
 * `id.timestamp.corpo` con il segreto `whsec_…` del webhook. Un timestamp
 * oltre i cinque minuti si rifiuta, o una notifica catturata si potrebbe
 * rigiocare all'infinito.
 *
 * Il webhook su Resend punta all'indirizzo `ondigitalocean.app` e non al
 * dominio: quello resta valido anche dopo il passaggio del 1 ottobre, quindi
 * non c'è niente da cambiare quel giorno.
 */
class ResendWebhookController extends Controller
{
    private const TOLLERANZA_SECONDI = 300;

    /** Un destinatario con un ordine in questa finestra è un cliente. */
    private const ORDINI_RECENTI_GIORNI = 30;

    /** Tetto globale agli avvisi per email che partono da qui. */
    public const AVVISI_ALL_ORA = 10;

    private const CHIAVE_TETTO = 'resend:avvisi-dell-ora';

    /**
     * Gli eventi che richiedono di fare qualcosa: la email non è arrivata e
     * non arriverà. `delivery_delayed` resta fuori, perché di solito si
     * risolve da sé.
     *
     * @var array<string, string>
     */
    private const EVENTI = [
        'email.bounced' => 'rimbalzata',
        'email.complained' => 'segnalata come spam',
        'email.failed' => 'non spedita',
    ];

    public function __construct(
        private readonly AvvisoTecnico $avviso,
    ) {}

    public function __invoke(Request $request): Response
    {
        if (! $this->firmaValida($request)) {
            return response('', 400);
        }

        $evento = (string) $request->json('type');

        if (! isset(self::EVENTI[$evento])) {
            return response('', 204);
        }

        /** @var list<string> $destinatari */
        $destinatari = array_values(array_filter((array) $request->json('data.to', []), 'is_string'));

        // Una email d'allarme che rimbalza produrrebbe un altro allarme verso
        // la stessa casella, e così via: quel caso resta a Sentry.
        if (array_intersect(array_map('strtolower', $destinatari), array_map('strtolower', AvvisoTecnico::destinatari())) !== []) {
            report(new \RuntimeException("Email d'allarme {$evento}: la casella degli avvisi non riceve."));

            return response('', 204);
        }

        $esito = self::EVENTI[$evento];
        $emailId = (string) $request->json('data.email_id', '—');
        $ordine = $this->ordineRecente($destinatari);

        if ($ordine === null) {
            Log::warning("Resend: email {$esito} verso un indirizzo senza ordini recenti", [
                'evento' => $evento,
                'email_id' => $emailId,
            ]);

            return response('', 204);
        }

        $memoria = AvvisoTecnico::memoria();

        // Il posto nel tetto si prende prima dell'invio: letto e incrementato
        // dopo, due notifiche arrivate insieme lo superavano entrambe. add()
        // apre la finestra dell'ora al primo avviso, increment() conta i
        // successivi senza spostarne la scadenza.
        $posto = $memoria->add(self::CHIAVE_TETTO, 1, 3600) ? 1 : (int) $memoria->increment(self::CHIAVE_TETTO);

        if ($posto > self::AVVISI_ALL_ORA) {
            Log::warning("Resend: email {$esito}, avviso non inviato: raggiunto il tetto orario", [
                'evento' => $evento,
                'email_id' => $emailId,
                'ordine' => $ordine->order_number,
            ]);

            return response('', 204);
        }

        $oggetto = Str::limit((string) $request->json('data.subject', ''), 120);
        $motivo = trim((string) $request->json('data.bounce.message', $request->json('data.failed.reason', '')));

        $inviato = $this->avviso->invia(
            "Email {$esito}: ".implode(', ', $destinatari),
            "Una email del sito non è arrivata ({$esito}).\n\n"
                .'Destinatario: '.implode(', ', $destinatari)."\n"
                ."Ordine: #{$ordine->order_number}\n"
                ."Oggetto: {$oggetto}\n"
                .($motivo !== '' ? 'Motivo: '.Str::limit($motivo, 500)."\n" : '')
                ."\nIl cliente va contattato per altra via. "
                ."Dettagli su Resend → Emails, id {$emailId}.",
            'email-non-consegnata:'.$evento.':'.strtolower(implode(',', $destinatari)),
            86400,
        );

        // Il posto preso vale solo per un avviso partito davvero.
        if ($inviato !== EsitoAvviso::Inviato) {
            $memoria->decrement(self::CHIAVE_TETTO);
        }

        return response('', 204);
    }

    /**
     * L'ordine più recente degli ultimi ORDINI_RECENTI_GIORNI giorni fatto da
     * uno dei destinatari, come ospite o con l'account.
     *
     * @param  list<string>  $destinatari
     */
    private function ordineRecente(array $destinatari): ?Order
    {
        $indirizzi = array_values(array_unique(array_map(fn (string $a): string => strtolower(trim($a)), $destinatari)));

        if ($indirizzi === []) {
            return null;
        }

        return Order::query()
            ->where('created_at', '>=', now()->subDays(self::ORDINI_RECENTI_GIORNI))
            ->where(fn (Builder $q) => $q
                ->whereIn(DB::raw('lower(guest_email)'), $indirizzi)
                ->orWhereHas('user', fn (Builder $u) => $u->whereIn(DB::raw('lower(email)'), $indirizzi)))
            ->latest('id')
            ->first();
    }

    private function firmaValida(Request $request): bool
    {
        $segreto = (string) config('services.resend.webhook_secret');
        $id = (string) $request->header('svix-id');
        $timestamp = (string) $request->header('svix-timestamp');
        $firme = (string) $request->header('svix-signature');

        if ($segreto === '' || $id === '' || ! ctype_digit($timestamp) || $firme === '') {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::TOLLERANZA_SECONDI) {
            return false;
        }

        $chiave = base64_decode(Str::after($segreto, 'whsec_'), true);

        if ($chiave === false) {
            return false;
        }

        $attesa = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.".$request->getContent(), $chiave, true));

        // L'intestazione può portare più firme ("v1,xxx v1,yyy") durante la
        // rotazione del segreto: ne basta una.
        foreach (explode(' ', $firme) as $firma) {
            if (hash_equals($attesa, Str::after($firma, ','))) {
                return true;
            }
        }

        return false;
    }
}
