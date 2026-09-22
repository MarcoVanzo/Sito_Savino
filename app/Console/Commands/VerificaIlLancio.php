<?php

namespace App\Console\Commands;

use App\Enums\PaymentGateway;
use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Console\Command;

/**
 * Dice se il sito e' pronto per il giorno in cui il dominio diventa suo.
 *
 * Il passaggio a `savinodelbenevolley.it` e' previsto per il 1 ottobre 2026.
 * Fino a quel momento il sito vive su un indirizzo `ondigitalocean.app` che
 * nessuno guarda, e diverse cose mancanti non si notano: la posta scritta nel
 * log invece che spedita, le chiavi di un gateway assenti, il negozio lasciato
 * chiuso. Il giorno del passaggio si notano tutte insieme, e su alcune c'e' di
 * mezzo il denaro dei clienti.
 *
 * Legge soltanto: nessuna impostazione viene toccata, nessuna credenziale
 * stampata. Si lancia dove vivono le variabili — in produzione dalla console
 * dell'app — e va lanciato **prima** di spostare il DNS, non dopo.
 *
 * Quello che qui non si puo' sapere e' detto dove sta: il webhook di PayPal
 * esiste davvero e punta a questo sito solo `paypal:verifica` puo' dirlo,
 * perche' va chiesto a PayPal.
 */
class VerificaIlLancio extends Command
{
    protected $signature = 'verifica:lancio';

    protected $description = 'Controlla cosa manca per andare online sul dominio definitivo';

    private const BLOCCO = 'blocco';

    private const AVVISO = 'avviso';

    private const OK = 'ok';

    /** @var list<array{esito: string, titolo: string, dettaglio: string, rimedio: string}> */
    private array $esiti = [];

    public function handle(): int
    {
        $this->line('');
        $this->line('  <options=bold>Prontezza al lancio</>');
        $this->line('');

        $this->ambiente();
        $this->chiaveApplicativa();
        $this->indirizzoPubblico();
        $this->accessoRiservato();
        $this->posta();
        $this->pagamenti();
        $this->negozio();
        $this->sorveglianza();
        $this->notizie();

        return $this->riepiloga();
    }

    private function ambiente(): void
    {
        $ambiente = (string) config('app.env');
        $debug = (bool) config('app.debug');

        // `APP_DEBUG` acceso in produzione mostra a chiunque sbagli un
        // indirizzo la traccia dello stack, con i percorsi dei file e i nomi
        // delle variabili d'ambiente.
        $this->registra(
            $debug && $ambiente === 'production' ? self::BLOCCO : self::OK,
            'Ambiente',
            "app.env = {$ambiente}, app.debug = ".($debug ? 'true' : 'false'),
            'APP_DEBUG deve essere false in produzione.',
        );
    }

    /**
     * La chiave con cui si cifra e si firma.
     *
     * Va controllata su OGNI componente, perche' web, worker e scheduler sono
     * tre ambienti distinti e una variabile scritta in uno non arriva agli
     * altri. Senza chiave non si rompe niente a vista: `encrypt()` e le firme
     * degli URL smettono semplicemente di combaciare con quelle del web, e
     * l'output dello scheduler finisce in `/dev/null`.
     */
    private function chiaveApplicativa(): void
    {
        $chiave = (string) config('app.key');

        $this->registra(
            $chiave === '' ? self::BLOCCO : self::OK,
            'Chiave applicativa',
            $chiave === '' ? 'APP_KEY assente' : 'presente ('.strlen($chiave).' caratteri)',
            'Senza APP_KEY i valori con cast `encrypted` non si leggono e i link firmati (la disiscrizione dalla newsletter) non si verificano. Da rilanciare sulla console di web, worker e scheduler: le variabili non sono condivise.',
        );
    }

    private function indirizzoPubblico(): void
    {
        $url = (string) config('app.url');

        // Tutto cio' che nasce fuori da una richiesta usa questo valore: i
        // link dentro le email in coda, la sitemap, il feed RSS che legge la
        // Lega, gli indirizzi di ritorno dei pagamenti.
        if ($url === '' || str_contains($url, 'localhost') || str_starts_with($url, 'http://')) {
            $this->registra(self::BLOCCO, 'Indirizzo pubblico', "app.url = {$url}",
                'APP_URL deve essere l\'indirizzo https definitivo: finisce nelle email, nella sitemap e nel feed.');

            return;
        }

        if (str_contains($url, 'ondigitalocean.app')) {
            $this->registra(self::AVVISO, 'Indirizzo pubblico', "app.url = {$url}",
                'Ancora l\'indirizzo provvisorio. Diventa quello vero quando il dominio entra fra i domini dell\'app (APP_URL vale https://${APP_DOMAIN}).');

            return;
        }

        $this->registra(self::OK, 'Indirizzo pubblico', "app.url = {$url}", '');
    }

    private function accessoRiservato(): void
    {
        $acceso = (bool) config('services.preview_auth.enabled');

        $this->registra(
            $acceso ? self::BLOCCO : self::OK,
            'Accesso riservato',
            $acceso ? 'la protezione con utente e password e\' ACCESA' : 'aperto al pubblico',
            'PREVIEW_AUTH_ENABLED deve essere false: acceso, il sito chiede le credenziali a chiunque, Googlebot compreso.',
        );
    }

    /**
     * La posta e' la voce che pesa di piu': senza, un cliente paga e non
     * riceve niente.
     */
    private function posta(): void
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array', ''], true)) {
            $this->registra(self::BLOCCO, 'Posta', "mail.default = {$mailer}",
                'Nessuna email esce: conferma d\'ordine, spedizione, rimborso, asta vinta o superata e reimpostazione della password finiscono nel log. Serve MAIL_MAILER (Resend e\' gia\' installato) con mittente e chiave.');

            return;
        }

        $mittente = (string) config('mail.from.address');

        if ($mittente === '' || str_contains($mittente, 'example')) {
            $this->registra(self::BLOCCO, 'Posta', "mailer {$mailer}, mittente \"{$mittente}\"",
                'MAIL_FROM_ADDRESS non e\' un mittente vero: le email verrebbero rifiutate dai destinatari.');

            return;
        }

        $this->registra(self::OK, 'Posta', "mailer {$mailer}, da {$mittente}", '');
    }

    private function pagamenti(): void
    {
        $configurati = array_values(array_filter(
            PaymentGateway::cases(),
            fn (PaymentGateway $gateway): bool => $gateway->configurato(),
        ));

        $nomi = implode(', ', array_map(fn (PaymentGateway $g): string => $g->value, $configurati));

        // Il bonifico e' sempre "configurato": da solo non e' un negozio.
        if (count($configurati) <= 1) {
            $this->registra(self::BLOCCO, 'Pagamenti', "attivi: {$nomi}",
                'Nessun pagamento elettronico ha le credenziali: resterebbe il solo bonifico.');
        } else {
            $this->registra(self::OK, 'Pagamenti', "con credenziali: {$nomi}", '');
        }

        $this->payPal();
        $this->stripe();
    }

    /**
     * Qui si controlla solo cio' che si vede da dentro. Se il webhook esiste
     * davvero, a quale indirizzo punta e quali eventi ascolta lo sa dire solo
     * `paypal:verifica`, che lo chiede a PayPal.
     */
    private function payPal(): void
    {
        if (! PaymentGateway::PayPal->configurato()) {
            return;
        }

        $modo = (string) config('services.paypal.mode');
        $webhook = (string) config('services.paypal.webhook_id');

        if ($webhook === '') {
            $this->registra(self::BLOCCO, 'Webhook PayPal', 'PAYPAL_WEBHOOK_ID non impostato',
                'Senza, la firma delle notifiche non si verifica e il pagamento non viene registrato.');

            return;
        }

        // L'id identifica un webhook registrato su un indirizzo preciso.
        // Cambiando dominio quel webhook continua a esistere, ma punta al
        // vecchio indirizzo: le notifiche non arrivano piu', gli ordini
        // restano "pending" e nessuno incassa. Non c'e' errore da nessuna
        // parte, solo ordini che non si chiudono.
        $this->registra(self::AVVISO, 'Webhook PayPal', "modo {$modo}, id {$webhook}",
            'Da confermare con `php artisan paypal:verifica`: e\' l\'unico modo di sapere se punta a QUESTO indirizzo. Cambiando dominio va rifatto e l\'id aggiornato in .do/app.yaml.');
    }

    private function stripe(): void
    {
        if (PaymentGateway::Stripe->configurato()) {
            $this->registra(self::OK, 'Stripe', 'chiavi presenti', '');

            return;
        }

        $this->registra(self::AVVISO, 'Stripe', 'chiavi assenti',
            'La carta di credito non viene offerta al cliente. E\' gestito, non rotto: se la si vuole al lancio servono le chiavi.');
    }

    private function negozio(): void
    {
        foreach (['shop.enabled' => 'Negozio', 'auctions.enabled' => 'Aste'] as $chiave => $titolo) {
            $acceso = (bool) SiteSetting::get($chiave, true);

            $this->registra(
                $acceso ? self::OK : self::AVVISO,
                $titolo,
                $acceso ? 'aperto' : 'chiuso',
                'Si riapre con `php artisan shop:stato '.($titolo === 'Negozio' ? 'negozio' : 'aste').' aperto`.',
            );
        }
    }

    private function sorveglianza(): void
    {
        $dsn = (string) config('sentry.dsn');

        $this->registra(
            $dsn === '' ? self::AVVISO : self::OK,
            'Sorveglianza errori',
            $dsn === '' ? 'Sentry spento' : 'Sentry attivo',
            'Senza DSN un errore in produzione non lo racconta nessuno, e LOG_LEVEL=error nasconde gli avvisi.',
        );
    }

    /**
     * Quanto e' indietro l'archivio rispetto a quello che la redazione
     * pubblica: finche' il dominio e' del vecchio sito, i comunicati nuovi
     * nascono la' (vedi `news:importa-dal-vecchio-sito`).
     */
    private function notizie(): void
    {
        $ultima = Post::max('published_at');

        if ($ultima === null) {
            $this->registra(self::BLOCCO, 'Notizie', 'archivio vuoto', 'Nessuna notizia pubblicata.');

            return;
        }

        $giorni = (int) now()->diffInDays($ultima, absolute: true);

        $this->registra(
            $giorni > 7 ? self::AVVISO : self::OK,
            'Notizie',
            "l'ultima e' di {$giorni} giorni fa",
            'Se il vecchio sito ne ha di piu\' recenti: `php artisan news:importa-dal-vecchio-sito --prova`.',
        );
    }

    /** "1 blocco" e non "1 blocchi": lo legge una persona, non un log. */
    private function conta(int $quanti, string $singolare, string $plurale): string
    {
        return $quanti.' '.($quanti === 1 ? $singolare : $plurale);
    }

    private function registra(string $esito, string $titolo, string $dettaglio, string $rimedio): void
    {
        $this->esiti[] = compact('esito', 'titolo', 'dettaglio', 'rimedio');

        $segno = match ($esito) {
            self::BLOCCO => '<fg=red;options=bold>✗</>',
            self::AVVISO => '<fg=yellow;options=bold>!</>',
            default => '<fg=green>✓</>',
        };

        $this->line(sprintf('  %s  %-22s %s', $segno, $titolo, $dettaglio));

        if ($esito !== self::OK && $rimedio !== '') {
            $this->line(sprintf('     <fg=gray>%s</>', $rimedio));
        }
    }

    private function riepiloga(): int
    {
        $blocchi = array_filter($this->esiti, fn (array $e): bool => $e['esito'] === self::BLOCCO);
        $avvisi = array_filter($this->esiti, fn (array $e): bool => $e['esito'] === self::AVVISO);

        $this->line('');

        if ($blocchi === []) {
            $this->info('  Nessun blocco. '.$this->conta(count($avvisi), 'cosa da guardare', 'cose da guardare').'.');

            return self::SUCCESS;
        }

        $this->error(sprintf(
            '  %s al lancio, %s.',
            $this->conta(count($blocchi), 'blocco', 'blocchi'),
            $this->conta(count($avvisi), 'cosa da guardare', 'cose da guardare'),
        ));
        $this->line('');

        foreach ($blocchi as $blocco) {
            $this->line("  · {$blocco['titolo']}: {$blocco['rimedio']}");
        }

        $this->line('');

        return self::FAILURE;
    }
}
