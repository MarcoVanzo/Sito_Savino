<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\VecchioSito\MediaDelVecchioSito;
use Illuminate\Console\Command;

/**
 * Porta sul disco del sito le immagini e i PDF che le notizie prendono ancora
 * dal sito precedente.
 *
 * Ventinove comunicati importati da WordPress citano ancora
 * `savinodelbenevolley.it/wp-content/uploads/...`: locandine, tabelle dei
 * prezzi, griglie di Coppa Italia, i calendari in PDF. Finche' il dominio
 * punta al vecchio sito quei file si vedono, ma il giorno della migrazione
 * l'indirizzo diventa quello del sito nuovo e ogni immagine si spegne — le
 * notizie restano, il loro contenuto no. Non e' un problema rimandabile al
 * momento del passaggio: il vecchio sito, una volta staccato, non e' piu'
 * interrogabile.
 *
 * Copia il file, poi riscrive il link: le due cose insieme, per file, cosi'
 * interrompersi a meta' non lascia in archivio un indirizzo che non porta a
 * niente. E' idempotente — un file gia' copiato non si riscarica e una notizia
 * gia' riscritta non cita piu' il vecchio dominio, quindi esce da sola dalla
 * selezione.
 *
 * Le regole di cosa si copia e dove stanno in `MediaDelVecchioSito`, che usa
 * anche l'import dei comunicati nuovi.
 */
class ImportaIMediaDelleNotizie extends Command
{
    protected $signature = 'news:importa-i-media-dal-vecchio-sito
        {--prova : elenca soltanto quello che farebbe, senza scaricare ne\' scrivere}
        {--forza : ricopia anche i file gia\' presenti sul disco}
        {--limite= : quante notizie al massimo}';

    protected $description = 'Copia sul disco del sito le immagini e i PDF che le notizie prendono dal vecchio sito';

    private MediaDelVecchioSito $media;

    /** @var list<string> */
    private array $falliti = [];

    public function handle(): int
    {
        $this->media = new MediaDelVecchioSito(
            prova: (bool) $this->option('prova'),
            forza: (bool) $this->option('forza'),
        );

        $notizie = Post::query()
            ->where(fn ($q) => $q->where('content', 'like', '%wp-content%')
                ->orWhere('excerpt', 'like', '%wp-content%'))
            ->orderBy('id');

        if ($this->option('limite') !== null) {
            $notizie->limit((int) $this->option('limite'));
        }

        $notizie = $notizie->get();

        if ($notizie->isEmpty()) {
            $this->info('Nessuna notizia prende media dal vecchio sito.');

            return self::SUCCESS;
        }

        $this->info($notizie->count().' notizie citano il vecchio sito.');
        $this->newLine();

        $riscritte = 0;

        foreach ($notizie as $notizia) {
            // Una notizia che non si salva non ferma le altre: i file gia'
            // copiati restano sul disco e un secondo giro riprende da dove si
            // era arrivati. Perdere ventotto riscritture per una riga storta
            // significherebbe rifare novanta scaricamenti.
            try {
                if ($this->riscriviLaNotizia($notizia)) {
                    $riscritte++;
                }
            } catch (\Throwable $errore) {
                $this->falliti[] = "notizia #{$notizia->id}: {$errore->getMessage()}";
                $this->warn("  ✗ #{$notizia->id} {$notizia->slug}: {$errore->getMessage()}");
            }

            $this->mostraGliAvvisi();
        }

        $this->riepiloga($riscritte);

        return $this->falliti === [] && $this->media->falliti() === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return bool la notizia e' cambiata
     */
    private function riscriviLaNotizia(Post $notizia): bool
    {
        $cambiata = false;

        foreach (['content', 'excerpt'] as $campo) {
            $traduzioni = $this->traduzioni($notizia, $campo);
            $nuove = $traduzioni;

            foreach ($traduzioni as $lingua => $testo) {
                if (! is_string($testo) || ! str_contains($testo, MediaDelVecchioSito::PREFISSO)) {
                    continue;
                }

                $nuove[$lingua] = $this->media->riscrivi($testo);
            }

            if ($nuove === $traduzioni) {
                continue;
            }

            $cambiata = true;

            if (! $this->option('prova')) {
                $notizia->setTranslations($campo, $nuove);
            }
        }

        if (! $cambiata) {
            return false;
        }

        if (! $this->option('prova')) {
            $notizia->save();
        }

        $this->line(sprintf('  %s #%d %s', $this->option('prova') ? '·' : '✓', $notizia->id, $notizia->slug));

        return true;
    }

    /**
     * Le traduzioni di un campo, anche quando la riga e' in testo semplice.
     *
     * In archivio convivono righe scritte come JSON per lingua e righe legacy
     * con il solo testo: `getTranslations()` sulle seconde restituisce un
     * elenco vuoto, e senza questo ripiego le notizie piu' vecchie sarebbero
     * state saltate in silenzio.
     *
     * @return array<string, mixed>
     */
    private function traduzioni(Post $notizia, string $campo): array
    {
        $traduzioni = $notizia->getTranslations($campo);

        if ($traduzioni !== []) {
            return $traduzioni;
        }

        $grezzo = $notizia->getRawOriginal($campo);

        return is_string($grezzo) && $grezzo !== ''
            ? [config('app.locale') => $grezzo]
            : [];
    }

    /**
     * Il servizio accumula gli avvisi invece di stamparli: qui si svuotano,
     * cosi' restano accanto alla notizia che li ha prodotti.
     */
    private function mostraGliAvvisi(): void
    {
        foreach ($this->media->avvisi() as $avviso) {
            $this->warn('    ⚠ '.$avviso);
        }

        $this->media->dimenticaGliAvvisi();
    }

    private function riepiloga(int $riscritte): void
    {
        $this->newLine();

        if ($this->option('prova')) {
            $this->info(sprintf(
                'Prova: %d notizie da riscrivere, %d file da copiare.',
                $riscritte,
                $this->media->copiati()
            ));

            return;
        }

        $this->info(sprintf(
            'Notizie riscritte: %d. File copiati: %d (%d gia\' presenti).',
            $riscritte,
            $this->media->scaricati(),
            $this->media->giaPresenti()
        ));

        $falliti = [...$this->falliti, ...$this->media->falliti()];

        if ($falliti !== []) {
            $this->newLine();
            $this->error(count($falliti).' file non copiati (il link e\' rimasto al vecchio sito):');

            foreach ($falliti as $indirizzo) {
                $this->line('  · '.$indirizzo);
            }
        }
    }
}
