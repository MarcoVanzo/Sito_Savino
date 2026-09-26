<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Controllo dell'impianto PayPal, senza incassare niente.
 *
 * Serve a rispondere alle tre domande che decidono se lo shop incassa o no:
 * le credenziali sono buone? il webhook configurato esiste davvero? punta a
 * questo sito e ascolta gli eventi che il codice sa gestire?
 *
 * Nessuna di queste si vede dall'esterno: il webhook risponde 400 sia quando
 * la firma non torna sia quando le credenziali sono sbagliate, quindi una
 * chiamata di prova non distingue un impianto sano da uno rotto. Va lanciato
 * dove vivono le variabili — in locale con le chiavi sandbox, in produzione
 * dalla console dell'app.
 *
 * Legge soltanto: chiede un token e interroga l'elenco dei webhook. Non
 * stampa mai credenziali o token.
 *
 * Esce con TRANSITORIO (3), non con FAILURE, quando è PayPal a non rispondere
 * (rete, timeout, 5xx, 429): `shop:sorveglia` lo tratta come "non so" invece
 * di annunciare un impianto "non configurato" che non lo è.
 */
class VerificaPayPal extends Command
{
    protected $signature = 'paypal:verifica';

    protected $description = 'Verifica credenziali PayPal, webhook registrato ed eventi sottoscritti';

    /** Eventi su cui il codice sa agire (PayPalPaymentService::handleWebhook). */
    private const EVENTI_ATTESI = [
        'CHECKOUT.ORDER.APPROVED',
        'PAYMENT.CAPTURE.REFUNDED',
    ];

    /** PayPal non ha risposto: nessuna conclusione sull'impianto. */
    public const TRANSITORIO = 3;

    /** Secondi di attesa per ogni chiamata. */
    private const TIMEOUT = 15;

    public function handle(): int
    {
        try {
            return $this->verifica();
        } catch (ConnectionException $e) {
            $this->warn('PayPal non raggiungibile ('.$e->getMessage().'): riprovare più tardi.');

            return self::TRANSITORIO;
        }
    }

    private function verifica(): int
    {
        $mode = (string) config('services.paypal.mode');
        $clientId = (string) config('services.paypal.client_id');
        $secret = (string) config('services.paypal.client_secret');
        $webhookId = (string) config('services.paypal.webhook_id');
        $base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        $this->line("Modalità: <info>{$mode}</info> ({$base})");

        if ($clientId === '' || $secret === '') {
            $this->error('PAYPAL_CLIENT_ID o PAYPAL_CLIENT_SECRET non impostati: lo shop non può aprire nessun pagamento.');

            return self::FAILURE;
        }

        $token = $this->token($base, $clientId, $secret);

        if (is_int($token)) {
            return $token;
        }

        $this->info('Credenziali valide: token ottenuto.');

        if ($webhookId === '') {
            $this->error('PAYPAL_WEBHOOK_ID non impostato: la firma delle notifiche non è verificabile e ogni webhook viene rifiutato.');

            return self::FAILURE;
        }

        return $this->controllaWebhook($base, $token, $webhookId);
    }

    /**
     * @return string|int il token, oppure il codice d'uscita
     */
    private function token(string $base, string $clientId, string $secret): string|int
    {
        $risposta = $this->http()
            ->withBasicAuth($clientId, $secret)
            ->asForm()
            ->post("{$base}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

        if ($this->transitorio($risposta)) {
            $this->warn('PayPal non risponde ('.$risposta->status().'): riprovare più tardi.');

            return self::TRANSITORIO;
        }

        if ($risposta->failed()) {
            $this->error('Credenziali rifiutate da PayPal ('.$risposta->status().'): '.$risposta->json('error_description', ''));

            return self::FAILURE;
        }

        return (string) $risposta->json('access_token');
    }

    private function http(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT)->connectTimeout(5);
    }

    /**
     * Un 5xx o un 429 dicono che PayPal è in difficoltà, non che le nostre
     * credenziali o il webhook siano sbagliati.
     */
    private function transitorio(Response $risposta): bool
    {
        return $risposta->serverError() || $risposta->status() === 429;
    }

    private function controllaWebhook(string $base, string $token, string $webhookId): int
    {
        $risposta = $this->http()->withToken($token)->acceptJson()->get("{$base}/v1/notifications/webhooks");

        if ($this->transitorio($risposta)) {
            $this->warn('Elenco webhook non disponibile ('.$risposta->status().'): riprovare più tardi.');

            return self::TRANSITORIO;
        }

        if ($risposta->failed()) {
            $this->error('Elenco webhook non leggibile ('.$risposta->status().').');

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $webhooks */
        $webhooks = (array) $risposta->json('webhooks', []);
        $nostro = collect($webhooks)->firstWhere('id', $webhookId);

        if (! $nostro) {
            $this->error("Il webhook {$webhookId} non esiste su questo account PayPal: le notifiche non arriveranno mai e nessun ordine verrà incassato.");
            $this->line('Registrati su questo account:');

            foreach ($webhooks as $w) {
                $this->line('  - '.$w['id'].'  '.$w['url']);
            }

            return self::FAILURE;
        }

        $atteso = route('paypal.webhook');
        $registrato = (string) ($nostro['url'] ?? '');

        $this->line("Webhook {$webhookId}: <info>{$registrato}</info>");

        $esito = self::SUCCESS;

        if ($registrato !== $atteso) {
            $this->warn("L'indirizzo registrato non è quello di questo sito ({$atteso}): le notifiche vanno altrove.");
            $esito = self::FAILURE;
        }

        $eventi = collect((array) ($nostro['event_types'] ?? []))->pluck('name')->all();
        $mancanti = array_diff(self::EVENTI_ATTESI, $eventi);

        if ($mancanti !== []) {
            $this->warn('Eventi non sottoscritti: '.implode(', ', $mancanti));
            $esito = self::FAILURE;
        } else {
            $this->info('Eventi sottoscritti: '.implode(', ', self::EVENTI_ATTESI));
        }

        return $esito;
    }
}
