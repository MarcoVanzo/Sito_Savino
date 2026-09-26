<?php

namespace App\Console\Commands;

use App\Enums\PaymentGateway;
use App\Models\SiteSetting;
use App\Services\AvvisoTecnico;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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
 * settimana produce due email, non mille. Lo stato precedente sta in cache;
 * `start.sh` la svuota a ogni rilascio, quindi un guasto che dura attraversa
 * il deploy e viene ricordato una volta in più. È il comportamento voluto.
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

    public function handle(): int
    {
        $this->interruttore('negozio', 'shop.enabled', 'Il negozio è chiuso', 'Il negozio è di nuovo aperto');
        $this->interruttore('aste', 'auctions.enabled', 'Le aste sono sospese', 'Le aste sono di nuovo attive');

        $this->controlla('metodi-di-pagamento', $this->problemaDeiPagamenti(), 'Il checkout non offre nessun metodo di pagamento');
        $this->controlla('coda', $this->problemaDellaCoda(), 'La coda dei job è ferma');

        $this->controlla('pagamento-aste', $this->problemaDelleAste(), 'Le aste non si possono pagare');
        $this->controlla('offerte-aste', $this->problemaDelleOfferte(), 'Le aste non accettano nuovi offerenti');

        if ($this->negozioAperto() && in_array(PaymentGateway::PayPal, PaymentGateway::offertiAlCheckout(), true)
            && Cache::add('sorveglianza:paypal-controllato', true, self::PAYPAL_OGNI_SECONDI)) {
            $paypal = $this->problemaDiPayPal();

            // `false` = la verifica non ha potuto rispondere (rete, timeout):
            // non è un guasto e nemmeno una guarigione, quindi niente email in
            // nessuna delle due direzioni.
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
     * dopo un rilascio scrive solo se trova l'interruttore spento.
     */
    private function interruttore(string $nome, string $chiave, string $spento, string $acceso): void
    {
        $ora = filter_var(SiteSetting::get($chiave, true), FILTER_VALIDATE_BOOLEAN);
        $chiaveCache = 'sorveglianza:interruttore:'.$nome;
        $prima = Cache::get($chiaveCache);

        Cache::forever($chiaveCache, $ora);
        $this->line(ucfirst($nome).': '.($ora ? 'acceso' : 'spento'));

        if ($prima === null) {
            // Primo giro dopo un rilascio (start.sh svuota la cache): lo stato
            // precedente non si conosce. Un interruttore acceso non merita
            // un'email; uno spento sì, perché il Salva che lo ha spento può
            // essere caduto proprio fra l'ultimo giro e il deploy — è il caso
            // del 21/09. Il silenziatore di un giorno evita che ogni rilascio
            // lo riannunci.
            if (! $ora) {
                $this->avviso->invia(
                    $spento,
                    $spento.".\n\nL'impostazione `{$chiave}` risulta spenta al primo controllo dopo un rilascio. "
                        .'Se non è voluto, si riaccende da Impostazioni Shop & Aste o con `php artisan shop:stato`.',
                    'interruttore:'.$nome.':0:dopo-rilascio',
                    86400,
                );
            }

            return;
        }

        if ((bool) $prima === $ora) {
            return;
        }

        $this->avviso->invia(
            $ora ? $acceso : $spento,
            ($ora ? $acceso : $spento).".\n\nL'impostazione `{$chiave}` è cambiata negli ultimi minuti. "
                .'Se non è stato fatto apposta, si ripristina da Impostazioni Shop & Aste o con `php artisan shop:stato`.',
            'interruttore:'.$nome.':'.($ora ? '1' : '0'),
            0,
        );
    }

    /**
     * Avvisa quando il guasto compare e quando rientra.
     */
    private function controlla(string $nome, ?string $problema, string $oggetto): void
    {
        $chiaveCache = 'sorveglianza:guasto:'.$nome;
        $eraGuasto = Cache::get($chiaveCache) === true;

        if ($problema === null) {
            $this->line($nome.': ok');

            if ($eraGuasto) {
                Cache::forget($chiaveCache);
                $this->avviso->invia('Risolto: '.lcfirst($oggetto), 'La condizione segnalata in precedenza non si presenta più.', 'risolto:'.$nome, 0);
            }

            return;
        }

        $this->warn($nome.': '.$problema);

        if ($eraGuasto) {
            return;
        }

        Cache::forever($chiaveCache, true);
        $this->avviso->invia($oggetto, $problema, 'guasto:'.$nome, 0);
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

    private function asteAccese(): bool
    {
        return filter_var(SiteSetting::get('auctions.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Il vincitore paga il lotto con Stripe o PayPal
     * (PaymentGateway::offertiAlleAste, il bonifico non sta nel termine
     * dell'asta): con le aste accese e nessuno dei due disponibile arriva in
     * fondo al checkout e non ha con che pagare.
     */
    private function problemaDelleAste(): ?string
    {
        if (! $this->asteAccese() || PaymentGateway::offertiAlleAste() !== []) {
            return null;
        }

        return 'Le aste sono accese ma il checkout del vincitore non offre nessun metodo di pagamento: '
            .'servono Stripe o PayPal con le credenziali e attivi in `shop.active_payment_gateways` '
            .'(Impostazioni Shop & Aste). Altrimenti sospendere le aste.';
    }

    /**
     * Per fare un'offerta serve una carta verificata, e la verifica passa da
     * Stripe (PaymentVerificationController, middleware `verified.payment`):
     * senza le sue chiavi il vincitore potrebbe pagare con PayPal, ma chi non
     * ha già una carta verificata non arriva nemmeno a offrire.
     */
    private function problemaDelleOfferte(): ?string
    {
        if (! $this->asteAccese() || PaymentGateway::Stripe->configurato()) {
            return null;
        }

        return 'Le aste sono accese ma Stripe non ha le credenziali: per offrire serve la verifica della carta, '
            .'che passa da Stripe, quindi chi non l\'ha già fatta non può partecipare. '
            .'Impostare le chiavi di Stripe nella spec o sospendere le aste.';
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
            Cache::forget('sorveglianza:paypal-controllato');

            return false;
        }

        if ($esito === self::SUCCESS) {
            return null;
        }

        return "`php artisan paypal:verifica` non passa: gli ordini PayPal rischiano di restare in attesa.\n\n".trim(Artisan::output());
    }
}
