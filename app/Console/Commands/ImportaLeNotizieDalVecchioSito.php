<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\VecchioSito\ImportatoreDelleNotizie;
use App\Services\VecchioSito\LettoreDelleNotizie;
use App\Services\VecchioSito\MediaDelVecchioSito;
use App\Services\VecchioSito\PulitoreDelContenuto;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Riallinea l'archivio delle notizie a quello del vecchio sito.
 *
 * Le 941 notizie in archivio erano entrate da un export statico di WordPress
 * salvato sul portatile, fermo al 2 luglio 2026 e oggi non piu' sul disco: di
 * fatto un import irripetibile. Intanto la redazione ha continuato a
 * pubblicare sul vecchio sito, e il sito nuovo si e' fermato al 26 giugno —
 * tre mesi di comunicati mancanti, che dal giorno in cui il feed RSS e' andato
 * online sono anche quello che la Lega riceve abbonandosi.
 *
 * Qui si legge invece `wp-json`, che il vecchio sito espone senza chiave: la
 * stessa fonte, ma interrogabile quando si vuole. Senza `--da` riparte dalla
 * notizia piu' recente in archivio, quindi rilanciarlo e' sempre lecito e non
 * duplica niente (la chiave e' `wp_id`).
 *
 * Va lanciato dove vivono il database e le chiavi di Spaces: in produzione
 * dalla console dell'app.
 */
class ImportaLeNotizieDalVecchioSito extends Command
{
    protected $signature = 'news:importa-dal-vecchio-sito
        {--da= : da quale data pubblicare (YYYY-MM-DD); senza, riparte dall\'ultima notizia in archivio}
        {--prova : elenca soltanto quello che farebbe, senza scaricare ne\' scrivere}
        {--forza : riscrive anche le notizie gia\' importate}
        {--limite= : quanti comunicati al massimo}';

    protected $description = 'Importa dal vecchio sito i comunicati pubblicati dopo l\'ultimo che abbiamo';

    private ImportatoreDelleNotizie $importatore;

    private int $importate = 0;

    private int $saltate = 0;

    /** @var list<string> */
    private array $falliti = [];

    public function handle(): int
    {
        $prova = (bool) $this->option('prova');
        $lettore = new LettoreDelleNotizie;
        $media = new MediaDelVecchioSito(prova: $prova);

        $this->importatore = new ImportatoreDelleNotizie(
            $lettore,
            (new PulitoreDelContenuto)->conosceGliSlug($this->slugInArchivio()),
            $media,
        );

        $da = $this->daQuando();
        $this->info('Comunicati pubblicati dopo il '.$da->format('d/m/Y H:i').'.');

        try {
            $comunicati = $lettore->comunicatiDopo($da, $this->limite());
        } catch (\Throwable $errore) {
            $this->error('Il vecchio sito non risponde: '.$errore->getMessage());

            return self::FAILURE;
        }

        if ($comunicati === []) {
            $this->info("L'archivio e' gia' allineato.");

            return self::SUCCESS;
        }

        $this->info(count($comunicati).' da leggere.');
        $this->newLine();

        foreach ($comunicati as $comunicato) {
            $this->importaUno($comunicato, $prova);
            $this->mostraGliAvvisi();
        }

        $this->riepiloga($media);

        return $this->falliti === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $comunicato
     */
    private function importaUno(array $comunicato, bool $prova): void
    {
        $wpId = (int) ($comunicato['id'] ?? 0);
        $slug = (string) ($comunicato['slug'] ?? '');
        $quando = substr((string) ($comunicato['date'] ?? ''), 0, 10);

        if ($prova) {
            $esistente = Post::where('wp_id', $wpId)->exists();
            $esistente ? $this->saltate++ : $this->importate++;
            $this->line(sprintf('  %s %s  #%d  %s', $esistente ? '·' : '+', $quando, $wpId, $slug));

            return;
        }

        try {
            $notizia = $this->importatore->importa($comunicato, (bool) $this->option('forza'));

            if ($notizia === null) {
                $this->saltate++;

                return;
            }

            $this->importate++;
            $this->line(sprintf('  ✓ %s  #%d  %s', $quando, $wpId, $notizia->slug));
        } catch (\Throwable $errore) {
            // Un comunicato storto non ferma gli altri: l'archivio resta senza
            // buchi fino a li' e un secondo giro riprende da dove si e'
            // arrivati.
            $this->falliti[] = "#{$wpId} {$slug}: {$errore->getMessage()}";
            $this->warn("  ✗ #{$wpId} {$slug}: {$errore->getMessage()}");
        }
    }

    /**
     * Da dove ripartire: la data della notizia piu' recente in archivio.
     *
     * Si guarda l'intero archivio, bozze comprese: una notizia riletta due
     * volte non fa danno (la chiave e' `wp_id`), ma saltarne una la perde per
     * sempre il giorno in cui il vecchio dominio passa al sito nuovo.
     */
    private function daQuando(): CarbonImmutable
    {
        $scelta = $this->option('da');

        if (is_string($scelta) && $scelta !== '') {
            return CarbonImmutable::parse($scelta);
        }

        $ultima = Post::max('published_at');

        return $ultima === null
            ? CarbonImmutable::create(2000, 1, 1)
            : CarbonImmutable::parse($ultima);
    }

    private function limite(): ?int
    {
        $limite = $this->option('limite');

        return is_string($limite) && $limite !== '' ? max(1, (int) $limite) : null;
    }

    /**
     * Gli slug che abbiamo, perche' il pulitore sappia quali link del vecchio
     * sito puo' far diventare interni.
     *
     * @return list<string>
     */
    private function slugInArchivio(): array
    {
        return Post::query()->pluck('slug')->all();
    }

    private function mostraGliAvvisi(): void
    {
        foreach ($this->importatore->avvisi() as $avviso) {
            $this->warn('    ⚠ '.$avviso);
        }

        $this->importatore->dimenticaGliAvvisi();
    }

    private function riepiloga(MediaDelVecchioSito $media): void
    {
        $this->newLine();

        if ($this->option('prova')) {
            $this->info(sprintf('Prova: %d da importare, %d gia\' in archivio.', $this->importate, $this->saltate));

            return;
        }

        $this->info(sprintf(
            'Importate: %d. Gia\' in archivio: %d. Copertine: %d. File dal vecchio sito: %d.',
            $this->importate,
            $this->saltate,
            $this->importatore->copertineScaricate(),
            $media->scaricati(),
        ));

        if ($this->falliti !== []) {
            $this->newLine();
            $this->error(count($this->falliti).' comunicati non importati:');

            foreach ($this->falliti as $fallito) {
                $this->line('  · '.$fallito);
            }
        }
    }
}
