<?php

namespace App\Console\Commands;

use App\Enums\EsitoAvviso;
use App\Enums\PaymentGateway;
use App\Models\SiteSetting;
use App\Services\AvvisoTecnico;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * I guasti del negozio che non sono errori.
 *
 * Sentry vede le eccezioni e `/up` vede database e cache, ma i guasti che sono
 * costati di più allo shop non erano né l'una né l'altra cosa: il 21/09/2026
 * un Salva del pannello ha spento negozio e aste e azzerato i metodi di
 * pagamento, e il sito ha continuato a rispondere 200 senza vendere niente.
 * Questo comando guarda lo stato, non gli errori, e scrive via AvvisoTecnico.
 *
 * Ogni controllo avvisa quando la condizione **cambia** — guasto comparso,
 * guasto rientrato — e non a ogni giro: un negozio chiuso apposta per una
 * settimana produce due email, non mille. Lo stato precedente sta nello store
 * `persistente` (AvvisoTecnico::memoria()), che il `cache:clear` di `start.sh`
 * non tocca: un guasto che dura attraversa il rilascio senza essere
 * riannunciato. Lo stato si scrive solo dopo un invio riuscito (o silenziato,
 * o senza destinatari): se Resend fallisce, il giro successivo riprova.
 *
 * Non guarda gli ordini in attesa di pagamento: sono quasi sempre checkout
 * abbandonati, `order:check-unpaid` li annulla dopo un'ora, e i casi in cui il
 * denaro è arrivato ma l'ordine non torna avvisano già da soli
 * (AdminNotificationService::notifyPaymentNeedsReview).
 */
class SorvegliaLoShop extends Command
{
    protected $signature = 'shop:sorveglia';

    protected $description = 'Controlla interruttori dello shop, metodi di pagamento, coda e PayPal, e avvisa per email quando qualcosa cambia';

    /**
     * Oltre questa attesa un job della coda `default` non è in ritardo: il
     * worker è fermo. Il worker gira con `--sleep=3` e smaltisce `default`
     * prima di `ai`, quindi in condizioni normali l'attesa è di secondi.
     */
    public const CODA_FERMA_DOPO_MINUTI = 15;

    /** PayPal si interroga una volta l'ora, non a ogni giro. */
    private const PAYPAL_OGNI_SECONDI = 3600;

    public function __construct(
        private readonly AvvisoTecnico $avviso,
    ) {
        parent::__construct();
    }

    private function memoria(): Repository
    {
        return AvvisoTecnico::memoria();
    }

    public function handle(): int
    {
        $this->interruttore('negozio', 'shop.enabled', 'Il negozio è chiuso', 'Il negozio è di nuovo aperto');
        $this->interruttore('aste', 'auctions.enabled', 'Le aste sono sospese', 'Le aste sono di nuovo attive');

        $this->controlla('metodi-di-pagamento', $this->problemaDeiPagamenti(), 'Il checkout non offre nessun metodo di pagamento');
        $this->controlla('coda', $this->problemaDellaCoda(), 'La coda dei job è ferma');

        $this->controlla('pagamento-aste', $this->problemaDelleAste(), 'Le aste non si possono pagare');

        if (! $this->negozioAperto() || ! in_array(PaymentGateway::PayPal, PaymentGateway::offertiAlCheckout(), true)) {
            // Il controllo non si applica (PayPal tolto dai metodi o negozio
            // chiuso): un guasto ricordato da prima non vale più, e se PayPal
            // torna rotto va riannunciato.
            $this->memoria()->forget('sorveglianza:guasto:paypal');
        } elseif ($this->memoria()->add('sorveglianza:paypal-controllato', true, self::PAYPAL_OGNI_SECONDI)) {
            $paypal = $this->problemaDiPayPal();

            // `false` = la verifica non ha potuto rispondere (rete, timeout,
            // 5xx/429 di PayPal): non è un guasto e nemmeno una guarigione,
            // quindi niente email in nessuna delle due direzioni.
            if ($paypal !== false) {
                $this->controlla('paypal', $paypal, 'PayPal non è configurato correttamente');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Gli interruttori si possono spegnere apposta: qui si avvisa del cambio,
     * in entrambe le direzioni, perché chi riceve l'email possa dire "sì, l'ho
     * chiesto io" o accorgersi che non l'ha chiesto nessuno. Il primo giro
     * senza stato precedente scrive solo se trova l'interruttore spento.
     */
    private function interruttore(string $nome, string $chiave, string $spento, string $acceso): void
    {
        $ora = filter_var(SiteSetting::get($chiave, true), FILTER_VALIDATE_BOOLEAN);
        $chiaveCache = 'sorveglianza:interruttore:'.$nome;
        $prima = $this->memoria()->get($chiaveCache);

        $this->line(ucfirst($nome).': '.($ora ? 'acceso' : 'spento'));

        if ($prima === null) {
            // Primo giro in assoluto (o store persistente svuotato): lo stato
            // precedente non si conosce. Un interruttore acceso non merita
            // un'email; uno spento sì, perché il Salva che lo ha spento può
            // essere caduto proprio fra l'ultimo giro e il deploy — è il caso
            // del 21/09. Il silenziatore di un giorno evita di riannunciarlo.
            $esito = $ora ? null : $this->avviso->invia(
                $spento,
                $spento.".\n\nL'impostazione `{$chiave}` risulta spenta al primo controllo della sorveglianza. "
                    .'Se non è voluto, si riaccende da Impostazioni Shop & Aste o con `php artisan shop:stato`.',
                'interruttore:'.$nome.':0:dopo-rilascio',
                86400,
            );

            $this->ricorda($chiaveCache, $ora, $esito);

            return;
        }

        if ((bool) $prima === $ora) {
            return;
        }

        $esito = $this->avviso->invia(
            $ora ? $acceso : $spento,
            ($ora ? $acceso : $spento).".\n\nL'impostazione `{$chiave}` è cambiata negli ultimi minuti. "
                .'Se non è stato fatto apposta, si ripristina da Impostazioni Shop & Aste o con `php artisan shop:stato`.',
            'interruttore:'.$nome.':'.($ora ? '1' : '0'),
            0,
        );

        $this->ricorda($chiaveCache, $ora, $esito);
    }

    /**
     * Scrive il nuovo stato solo se l'avviso non è fallito: altrimenti il
     * giro dopo trova ancora lo stato vecchio e riprova.
     */
    private function ricorda(string $chiaveCache, bool $ora, ?EsitoAvviso $esito): void
    {
        if ($esito === null || $esito->chiuso()) {
            $this->memoria()->forever($chiaveCache, $ora);
        }
    }

    /**
     * Avvisa quando il guasto compare e quando rientra.
     */
    private function controlla(string $nome, ?string $problema, string $oggetto): void
    {
        $chiaveCache = 'sorveglianza:guasto:'.$nome;
        $eraGuasto = $this->memoria()->get($chiaveCache) === true;

        if ($problema === null) {
            $this->line($nome.': ok');

            if ($eraGuasto && $this->avviso->invia('Risolto: '.lcfirst($oggetto), 'La condizione segnalata in precedenza non si presenta più.', 'risolto:'.$nome, 0)->chiuso()) {
                $this->memoria()->forget($chiaveCache);
            }

            return;
        }

        $this->warn($nome.': '.$problema);

        if ($eraGuasto) {
            return;
        }

        if ($this->avviso->invia($oggetto, $problema, 'guasto:'.$nome, 0)->chiuso()) {
            $this->memoria()->forever($chiaveCache, true);
        }
    }

    private function negozioAperto(): bool
    {
        return filter_var(SiteSetting::get('shop.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function problemaDeiPagamenti(): ?string
    {
        // A negozio chiuso il checkout non si raggiunge: l'avviso del
        // negozio chiuso basta.
        if (! $this->negozioAperto() || PaymentGateway::offertiAlCheckout() !== []) {
            return null;
        }

        return 'Il negozio è aperto ma al checkout non compare nessun metodo di pagamento: un cliente arriva in fondo e non può pagare. '
            .'Controllare `shop.active_payment_gateways` (Impostazioni Shop & Aste) e le credenziali dei gateway nella spec.';
    }

    /**
     * Il checkout delle aste passa solo da Stripe (AuctionCheckoutController):
     * con le aste accese e Stripe senza credenziali il vincitore arriva in
     * fondo, l'ordine si crea e il pagamento non parte.
     */
    private function problemaDelleAste(): ?string
    {
        $asteAccese = filter_var(SiteSetting::get('auctions.enabled', true), FILTER_VALIDATE_BOOLEAN);

        if (! $asteAccese || PaymentGateway::Stripe->configurato()) {
            return null;
        }

        return 'Le aste sono accese ma Stripe non ha le credenziali: il checkout delle aste passa solo da Stripe, '
            .'quindi un vincitore non può pagare. Impostare le chiavi di Stripe nella spec o sospendere le aste.';
    }

    private function problemaDellaCoda(): ?string
    {
        $soglia = now()->subMinutes(self::CODA_FERMA_DOPO_MINUTI)->getTimestamp();

        $inAttesa = DB::table('jobs')
            ->where('queue', 'default')
            ->whereNull('reserved_at')
            ->where('available_at', '<', $soglia)
            ->count();

        if ($inAttesa === 0) {
            return null;
        }

        return "{$inAttesa} job della coda `default` aspettano da più di ".self::CODA_FERMA_DOPO_MINUTI.' minuti: '
            .'il worker è fermo. Conferme d\'ordine, spedizioni ed email delle aste non partono. '
            .'Si riavvia il componente `worker` da DigitalOcean.';
    }

    /**
     * `paypal:verifica` sa già rispondere: credenziali, webhook esistente,
     * indirizzo e eventi. Qui se ne prende l'esito e il testo.
     */
    private function problemaDiPayPal(): string|false|null
    {
        try {
            $esito = Artisan::call('paypal:verifica');
        } catch (\Throwable $e) {
            report($e);

            // Si riprova al giro dopo invece di aspettare un'ora.
            $this->memoria()->forget('sorveglianza:paypal-controllato');

            return false;
        }

        if ($esito === VerificaPayPal::TRANSITORIO) {
            // PayPal in difficoltà (5xx, 429, timeout): "non so", come sopra.
            $this->memoria()->forget('sorveglianza:paypal-controllato');

            return false;
        }

        if ($esito === self::SUCCESS) {
            return null;
        }

        return "`php artisan paypal:verifica` non passa: gli ordini PayPal rischiano di restare in attesa.\n\n".trim(Artisan::output());
    }
}
