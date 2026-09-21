<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
 */
class ImportaIMediaDelleNotizie extends Command
{
    protected $signature = 'news:importa-i-media-dal-vecchio-sito
        {--prova : elenca soltanto quello che farebbe, senza scaricare ne\' scrivere}
        {--forza : ricopia anche i file gia\' presenti sul disco}
        {--limite= : quante notizie al massimo}';

    protected $description = 'Copia sul disco del sito le immagini e i PDF che le notizie prendono dal vecchio sito';

    /**
     * Gli unici host da cui si accetta di scaricare.
     *
     * Gli indirizzi arrivano dal contenuto delle notizie, non da noi: senza
     * questo elenco il comando scaricherebbe qualunque cosa un link citi.
     */
    private const DOMINI = ['savinodelbenevolley.it', 'www.savinodelbenevolley.it'];

    /** L'archivio dei media di WordPress: il resto del vecchio sito sono pagine. */
    private const PREFISSO = '/wp-content/uploads/';

    private const CARTELLA = 'news';

    /**
     * Quello che una notizia puo' contenere: immagini, i calendari in PDF e i
     * comunicati delle cartelle stampa, che la redazione allega in ODT.
     * Un'estensione fuori elenco non si copia e il link resta com'e': meglio un
     * link visibilmente vecchio che un file di tipo imprevisto servito dal
     * nostro dominio.
     */
    private const ESTENSIONI = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'odt', 'doc', 'docx'];

    /** @var array<string, string> indirizzo sul vecchio sito => indirizzo sul nostro disco */
    private array $copiati = [];

    /** @var list<string> indirizzi che non si sono potuti copiare */
    private array $falliti = [];

    private int $scaricati = 0;

    private int $giaPresenti = 0;

    public function handle(): int
    {
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
        }

        $this->riepiloga($riscritte);

        return $this->falliti === [] ? self::SUCCESS : self::FAILURE;
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
                if (! is_string($testo) || ! str_contains($testo, self::PREFISSO)) {
                    continue;
                }

                $nuove[$lingua] = $this->riscriviIlTesto($testo);
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
     * Riscrive gli indirizzi dentro un pezzo di HTML redazionale.
     */
    private function riscriviIlTesto(string $html): string
    {
        $html = $this->togliLeVariantiDiWordPress($html);

        $html = preg_replace_callback(
            '/\b(src|href)="([^"]+)"/i',
            function (array $pezzi): string {
                $nuovo = $this->indirizzoNuovo($pezzi[2]);

                return $nuovo === null ? $pezzi[0] : $pezzi[1].'="'.$nuovo.'"';
            },
            $html
        ) ?? $html;

        return $this->riscriviGliIndirizziNelTesto($html);
    }

    /**
     * Riscrive anche gli indirizzi rimasti nel testo visibile.
     *
     * Quando in redazione si incolla un link nudo, WordPress usa l'indirizzo
     * stesso come etichetta: l'attributo `href` lo sistema il passaggio sopra,
     * il testo fra i due tag no. Il lettore si trova scritto in pagina
     * l'indirizzo di un dominio che sta per cambiare padrone, mentre il file
     * che scarica arriva gia' dal nostro disco. E' successo al Bilancio di
     * Sostenibilita' (notizia #169). Qui l'etichetta si allinea allo stesso
     * file.
     *
     * L'indirizzo deve finire con una delle estensioni ammesse: senza quel
     * vincolo la punteggiatura di fine frase entrerebbe nel nome del file.
     */
    private function riscriviGliIndirizziNelTesto(string $html): string
    {
        $domini = implode('|', array_map(fn (string $dominio): string => preg_quote($dominio, '~'), self::DOMINI));
        $estensioni = implode('|', self::ESTENSIONI);

        return preg_replace_callback(
            '~https?://(?:'.$domini.')'.preg_quote(self::PREFISSO, '~').'[^\s"\'<>]+?\.(?:'.$estensioni.')~i',
            fn (array $pezzi): string => $this->indirizzoNuovo($pezzi[0]) ?? $pezzi[0],
            $html
        ) ?? $html;
    }

    /**
     * Toglie `srcset` e `sizes` dalle immagini del vecchio sito.
     *
     * WordPress elenca li' cinque o sei ritagli dello stesso file: sono un
     * centinaio di indirizzi che il sito non usa nemmeno, perche' il
     * sanificatore del frontend (`useSanitize`) non ammette quei due attributi
     * e il browser vede solo `src`. Copiare quei ritagli sarebbe lavoro e
     * spazio per niente; lasciarli scritti com'erano terrebbe in archivio
     * altrettanti link a un dominio che non risponde piu'.
     */
    private function togliLeVariantiDiWordPress(string $html): string
    {
        return preg_replace_callback(
            '/<img\b[^>]*>/i',
            function (array $pezzi): string {
                $tag = $pezzi[0];

                if (! $this->citaIlVecchioSito($tag)) {
                    return $tag;
                }

                return preg_replace('/\s+(?:srcset|sizes)="[^"]*"/i', '', $tag) ?? $tag;
            },
            $html
        ) ?? $html;
    }

    private function citaIlVecchioSito(string $testo): bool
    {
        foreach (self::DOMINI as $dominio) {
            if (str_contains($testo, $dominio.self::PREFISSO)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Copia il file e restituisce il suo indirizzo sul nostro disco, oppure
     * null se l'indirizzo non e' da toccare o la copia non e' riuscita.
     */
    private function indirizzoNuovo(string $indirizzo): ?string
    {
        if (array_key_exists($indirizzo, $this->copiati)) {
            return $this->copiati[$indirizzo];
        }

        $percorso = $this->percorsoDiDestinazione($indirizzo);

        if ($percorso === null) {
            return null;
        }

        if ($this->option('prova')) {
            return $this->copiati[$indirizzo] = Storage::url($percorso);
        }

        if (! $this->option('forza') && Storage::exists($percorso)) {
            $this->giaPresenti++;

            return $this->copiati[$indirizzo] = Storage::url($percorso);
        }

        $contenuto = $this->scarica($indirizzo);

        if ($contenuto === null) {
            $this->falliti[] = $indirizzo;

            return null;
        }

        // `public` esplicito: su Spaces un oggetto scritto senza visibilita'
        // nasce privato e risponde 403 a chi legge la notizia.
        Storage::put($percorso, $contenuto, 'public');
        $this->scaricati++;

        return $this->copiati[$indirizzo] = Storage::url($percorso);
    }

    /**
     * Dove finisce il file, oppure null se l'indirizzo non e' un media del
     * vecchio sito.
     *
     * Conserva l'anno e il mese di WordPress: due locandine possono chiamarsi
     * entrambe `Tabella-costi.jpg` e senza quella cartella la seconda
     * sovrascriverebbe la prima. E' anche cio' che rende la copia idempotente,
     * perche' lo stesso indirizzo ricade sempre sullo stesso percorso.
     */
    private function percorsoDiDestinazione(string $indirizzo): ?string
    {
        $pezzi = parse_url($indirizzo);
        $host = $pezzi['host'] ?? null;
        $percorso = $pezzi['path'] ?? '';

        if (! in_array($host, self::DOMINI, true) || ! str_starts_with($percorso, self::PREFISSO)) {
            return null;
        }

        $dentro = rawurldecode(substr($percorso, strlen(self::PREFISSO)));
        $nome = basename($dentro);
        $estensione = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

        if (! in_array($estensione, self::ESTENSIONI, true)) {
            $this->warn("    ⚠ tipo non previsto, lascio il link com'e': {$indirizzo}");

            return null;
        }

        // Il resto del percorso e' `2022/09`: si tiene solo se ha quella forma,
        // perche' viene da un indirizzo scritto in una notizia.
        $cartella = preg_match('#^(\d{4}/\d{2})/#', $dentro, $data) === 1 ? $data[1] : 'altri';

        return self::CARTELLA.'/'.$cartella.'/'.$this->nomePulito($nome);
    }

    /**
     * Il nome del file si conserva — e' quello che il lettore vede scaricando
     * un calendario — ma ridotto ai caratteri che un indirizzo regge senza
     * codifica.
     */
    private function nomePulito(string $nome): string
    {
        $pulito = preg_replace('/[^A-Za-z0-9._-]+/', '-', $nome) ?? $nome;
        $pulito = preg_replace('/-{2,}/', '-', $pulito) ?? $pulito;

        return trim($pulito, '-') ?: 'file';
    }

    private function scarica(string $indirizzo): ?string
    {
        try {
            $risposta = Http::timeout(60)->retry(2, 1000, throw: false)->get($indirizzo);
        } catch (\Throwable $errore) {
            $this->warn("    ⚠ {$indirizzo}: {$errore->getMessage()}");

            return null;
        }

        if (! $risposta->successful()) {
            $this->warn("    ⚠ {$indirizzo}: HTTP {$risposta->status()}");

            return null;
        }

        $contenuto = $risposta->body();

        // Il vecchio sito risponde 200 anche alla pagina "non trovato": senza
        // questo controllo in archivio finirebbe l'HTML di quella pagina
        // sotto il nome della locandina, e il link sembrerebbe a posto.
        if (! $this->eDavveroUnFile($indirizzo, $contenuto)) {
            $this->warn("    ⚠ {$indirizzo}: la risposta non e' il file atteso");

            return null;
        }

        return $contenuto;
    }

    /**
     * Ogni formato si riconosce dai primi byte: ODT e DOCX sono archivi ZIP,
     * DOC e' un contenitore OLE2. Il confronto e' sulla firma e non sul
     * `Content-Type`, che il vecchio sito dichiara a modo suo.
     */
    private function eDavveroUnFile(string $indirizzo, string $contenuto): bool
    {
        if ($contenuto === '') {
            return false;
        }

        $estensione = strtolower(pathinfo((string) parse_url($indirizzo, PHP_URL_PATH), PATHINFO_EXTENSION));

        return match ($estensione) {
            'pdf' => str_starts_with($contenuto, '%PDF-'),
            'odt', 'docx' => str_starts_with($contenuto, "PK\x03\x04"),
            'doc' => str_starts_with($contenuto, "\xD0\xCF\x11\xE0"),
            default => @getimagesizefromstring($contenuto) !== false,
        };
    }

    private function riepiloga(int $riscritte): void
    {
        $this->newLine();

        if ($this->option('prova')) {
            $this->info(sprintf(
                'Prova: %d notizie da riscrivere, %d file da copiare.',
                $riscritte,
                count($this->copiati)
            ));

            return;
        }

        $this->info(sprintf(
            'Notizie riscritte: %d. File copiati: %d (%d gia\' presenti).',
            $riscritte,
            $this->scaricati,
            $this->giaPresenti
        ));

        if ($this->falliti !== []) {
            $this->newLine();
            $this->error(count($this->falliti).' file non copiati (il link e\' rimasto al vecchio sito):');

            foreach ($this->falliti as $indirizzo) {
                $this->line('  · '.$indirizzo);
            }
        }
    }
}
