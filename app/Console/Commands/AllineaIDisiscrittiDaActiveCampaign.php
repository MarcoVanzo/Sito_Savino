<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use App\Services\ActiveCampaignException;
use App\Services\ActiveCampaignService;
use Illuminate\Console\Command;

/**
 * Porta sul sito le disiscrizioni fatte su ActiveCampaign.
 *
 * Chi esce dal Preference Center o dal link in fondo a una campagna esce solo
 * su ActiveCampaign: senza un webhook la riga del sito restava "iscritta", con
 * nome e IP. Qui si chiede ad ActiveCampaign l'elenco dei disiscritti dalla
 * lista e si chiama unsubscribe() sulle righe corrispondenti, che cancella
 * nome e IP e tiene la prova per ventiquattro mesi.
 *
 * Si toccano solo le righe che il sito crede già sulla lista (confermate e
 * `synced_to_ac`): chi ha appena riconfermato dal sito e aspetta il job che lo
 * rimette in lista risulta ancora disiscritto su ActiveCampaign, e toglierlo
 * adesso annullerebbe la sua conferma.
 *
 * Errori transitori di ActiveCampaign (rete, 429, 5xx) non fanno fallire il
 * comando: domani riprova. Un errore vero (chiave rifiutata, lista che non
 * esiste, risposta illeggibile) sì, e l'avviso del pianificatore lo dice
 * (CLAUDE.md §24).
 */
class AllineaIDisiscrittiDaActiveCampaign extends Command
{
    protected $signature = 'newsletter:allinea-disiscritti
        {--prova : Dice chi disiscriverebbe senza toccare niente}';

    protected $description = 'Segna sul sito le disiscrizioni fatte su ActiveCampaign (Preference Center, link delle campagne)';

    public function handle(ActiveCampaignService $activeCampaign): int
    {
        if (! $activeCampaign->isConfigured()) {
            $this->info('ActiveCampaign non configurato: niente da allineare.');

            return self::SUCCESS;
        }

        try {
            $disiscritte = $activeCampaign->emailDisiscritteDallaLista();
        } catch (ActiveCampaignException $e) {
            if (self::transitorio($e->getCode())) {
                $this->warn('ActiveCampaign non risponde adesso ('.$e->getMessage().'): si riprova al prossimo giro.');

                return self::SUCCESS;
            }

            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $daSegnare = collect($disiscritte)
            ->chunk(500)
            ->flatMap(fn ($blocco) => NewsletterSubscriber::query()
                ->active()
                ->confermati()
                ->synced()
                ->whereIn('email', $blocco->all())
                ->get());

        if ($this->option('prova')) {
            $this->info($daSegnare->count().' iscritti da segnare come disiscritti (prova: niente è cambiato).');

            return self::SUCCESS;
        }

        $segnati = $daSegnare
            ->filter(fn (NewsletterSubscriber $iscritto) => $iscritto->unsubscribe(NewsletterSubscriber::DISISCRITTO_SU_ACTIVECAMPAIGN))
            ->count();

        // Nel output niente indirizzi: finisce nell'email dell'avviso.
        $this->info(sprintf(
            '%d disiscritti su ActiveCampaign, %d segnati ora anche sul sito.',
            count($disiscritte),
            $segnati,
        ));

        return self::SUCCESS;
    }

    /**
     * Rete assente (0), troppe richieste, timeout del gateway, errori del
     * server: passano da soli.
     */
    private static function transitorio(int $stato): bool
    {
        return $stato === 0 || $stato === 408 || $stato === 429 || $stato >= 500;
    }
}
