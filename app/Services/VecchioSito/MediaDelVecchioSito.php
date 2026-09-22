<?php

namespace App\Services\VecchioSito;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Porta sul disco del sito i file che un contenuto prende ancora dal vecchio
 * sito, e ne riscrive i link.
 *
 * Serve a due padroni: il comando che ripara le notizie gia' in archivio
 * (`news:importa-i-media-dal-vecchio-sito`) e quello che importa i comunicati
 * nuovi (`news:importa-dal-vecchio-sito`), che li fa passare di qui prima di
 * salvarli — una notizia non deve mai nascere con un link a un dominio che sta
 * per cambiare padrone. Le regole di cosa si copia e dove stanno scritte una
 * volta sola: quando il conto della spedizione e' finito in due file, la copia
 * dell'asta e' rimasta indietro alle fasce di peso per settimane.
 *
 * E' idempotente — un file gia' copiato non si riscarica e un testo gia'
 * riscritto non cita piu' il vecchio dominio.
 */
class MediaDelVecchioSito
{
    /**
     * Gli unici host da cui si accetta di scaricare.
     *
     * Gli indirizzi arrivano dal contenuto delle notizie, non da noi: senza
     * questo elenco si scaricherebbe qualunque cosa un link citi.
     */
    public const DOMINI = ['savinodelbenevolley.it', 'www.savinodelbenevolley.it'];

    /** L'archivio dei media di WordPress: il resto del vecchio sito sono pagine. */
    public const PREFISSO = '/wp-content/uploads/';

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

    /** @var list<string> quello che il chiamante deve poter mostrare a schermo */
    private array $avvisi = [];

    private int $scaricati = 0;

    private int $giaPresenti = 0;

    public function __construct(
        private readonly bool $prova = false,
        private readonly bool $forza = false,
    ) {}

    /**
     * Copia i file citati da un pezzo di HTML redazionale e restituisce lo
     * stesso HTML con gli indirizzi nuovi.
     */
    public function riscrivi(string $html): string
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
     * Se un testo abbia qualcosa da riscrivere, senza toccare la rete.
     */
    public static function citaIlVecchioSito(string $testo): bool
    {
        foreach (self::DOMINI as $dominio) {
            if (str_contains($testo, $dominio.self::PREFISSO)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function falliti(): array
    {
        return $this->falliti;
    }

    /** @return list<string> */
    public function avvisi(): array
    {
        return $this->avvisi;
    }

    /**
     * Svuota gli avvisi gia' mostrati.
     *
     * Il servizio non stampa niente — lo stesso codice serve un comando e un
     * import silenzioso — quindi li accumula; chi li ha letti li toglie, o alla
     * notizia seguente ricomparirebbero tutti.
     */
    public function dimenticaGliAvvisi(): void
    {
        $this->avvisi = [];
    }

    public function scaricati(): int
    {
        return $this->scaricati;
    }

    public function giaPresenti(): int
    {
        return $this->giaPresenti;
    }

    /** Quanti indirizzi distinti sono stati ricondotti al nostro disco. */
    public function copiati(): int
    {
        return count($this->copiati);
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

                if (! self::citaIlVecchioSito($tag)) {
                    return $tag;
                }

                return preg_replace('/\s+(?:srcset|sizes)="[^"]*"/i', '', $tag) ?? $tag;
            },
            $html
        ) ?? $html;
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

        if ($this->prova) {
            return $this->copiati[$indirizzo] = Storage::url($percorso);
        }

        if (! $this->forza && Storage::exists($percorso)) {
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
            $this->avvisi[] = "tipo non previsto, lascio il link com'e': {$indirizzo}";

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
            $this->avvisi[] = "{$indirizzo}: {$errore->getMessage()}";

            return null;
        }

        if (! $risposta->successful()) {
            $this->avvisi[] = "{$indirizzo}: HTTP {$risposta->status()}";

            return null;
        }

        $contenuto = $risposta->body();

        // Il vecchio sito risponde 200 anche alla pagina "non trovato": senza
        // questo controllo in archivio finirebbe l'HTML di quella pagina
        // sotto il nome della locandina, e il link sembrerebbe a posto.
        if (! $this->eDavveroUnFile($indirizzo, $contenuto)) {
            $this->avvisi[] = "{$indirizzo}: la risposta non e' il file atteso";

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
}
