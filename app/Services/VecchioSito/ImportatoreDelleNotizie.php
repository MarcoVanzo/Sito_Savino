<?php

namespace App\Services\VecchioSito;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Porta in archivio un comunicato letto dal vecchio sito.
 *
 * La chiave naturale e' `wp_id`: e' l'identificativo del post su WordPress, ce
 * l'hanno tutte e 943 le righe gia' in archivio, e rilanciare l'import non
 * duplica niente. Lo slug e' la seconda strada, per il caso in cui la
 * redazione abbia gia' scritto a mano lo stesso comunicato.
 */
class ImportatoreDelleNotizie
{
    /**
     * Etichette che non si importano: ripetono il nome della societa' o lo
     * sport, e comparirebbero in fondo a ogni comunicato senza dire niente.
     *
     * E' lo stesso filtro con cui sono entrate le 1626 etichette in archivio.
     */
    private const ETICHETTE_ESCLUSE = [
        'savino-del-bene-volley', 'savino-del-bene-scandicci', 'savino-del-bene',
        'pallavolo', 'volley', 'volleyball', 'serie-a1', 'femminile',
    ];

    /** Punteggi, stagioni e sigle: '3-0', '2026-2027', 'A1'. */
    private const ETICHETTE_SPAZZATURA = [
        '/^\d{4}$/',
        '/^\d{4}-\d{4}$/',
        '/^\d+-\d+$/',
        '/^[a-z]\d+$/i',
    ];

    /** @var array<int, ?int> identificativo WordPress => id da noi (null = da saltare) */
    private array $categorieRisolte = [];

    /** @var array<int, ?int> */
    private array $etichetteRisolte = [];

    private int $copertineScaricate = 0;

    /** @var list<string> */
    private array $avvisi = [];

    public function __construct(
        private readonly LettoreDelleNotizie $lettore,
        private readonly PulitoreDelContenuto $pulitore,
        private readonly MediaDelVecchioSito $media,
    ) {}

    /**
     * Scrive il comunicato in archivio e restituisce la notizia, oppure null se
     * c'era gia' e non si e' chiesto di rifarla.
     *
     * @param  array<string, mixed>  $comunicato
     */
    public function importa(array $comunicato, bool $forza = false): ?Post
    {
        $wpId = (int) ($comunicato['id'] ?? 0);

        if ($wpId <= 0) {
            throw new \InvalidArgumentException('Comunicato senza identificativo WordPress.');
        }

        $giaImportata = Post::where('wp_id', $wpId)->first();

        if ($giaImportata !== null && ! $forza) {
            return null;
        }

        $notizia = $giaImportata ?? $this->daAdottare($comunicato, $wpId);

        $titolo = $this->pulitore->titolo($this->reso($comunicato, 'title'));
        $contenuto = $this->reso($comunicato, 'content');
        $sommario = $this->reso($comunicato, 'excerpt');

        // La pulizia viene prima: toglie gli `srcset` e le classi di WordPress,
        // cosi' quello che resta da riscrivere sono i soli indirizzi veri.
        $contenuto = $this->media->riscrivi($this->pulitore->contenuto($contenuto));
        $sommario = $this->media->riscrivi($this->pulitore->sommario($sommario, $contenuto));

        $dati = [
            'wp_id' => $wpId,
            'title' => ['it' => $titolo],
            'slug' => $this->slug($comunicato, $titolo, $notizia),
            'content' => ['it' => $contenuto],
            'excerpt' => ['it' => $sommario],
            'status' => PostStatus::Published,
            'published_at' => $comunicato['date'] ?? null,
            'meta_title' => $this->metaTitolo($comunicato),
            'meta_description' => $this->metaDescrizione($comunicato),
        ];

        // Categorie ed etichette si risolvono prima della transazione: la
        // risoluzione interroga il vecchio sito, e una chiamata di rete non
        // deve tenere aperta una transazione.
        $categorie = $this->categorie($comunicato['categories'] ?? []);
        $etichette = $this->etichette($comunicato['tags'] ?? []);

        $notizia = DB::transaction(function () use ($notizia, $dati, $categorie, $etichette): Post {
            $notizia = $notizia === null
                ? Post::create($dati)
                : tap($notizia)->update($dati);

            // `sync` solo quando c'e' qualcosa: un comunicato senza categorie
            // non deve staccare quelle che la redazione ha messo a mano.
            if ($categorie !== []) {
                $notizia->categories()->sync($categorie);
            }

            if ($etichette !== []) {
                $notizia->tags()->sync($etichette);
            }

            return $notizia;
        });

        // La copertina viene dopo, e fuori: si scarica dal vecchio sito, e un
        // fallimento non deve annullare il comunicato gia' scritto — il testo
        // vale piu' della figura, e un secondo giro con `--forza` la recupera.
        $this->copertina($notizia, (int) ($comunicato['featured_media'] ?? 0), $forza);

        return $notizia;
    }

    public function copertineScaricate(): int
    {
        return $this->copertineScaricate;
    }

    /** @return list<string> */
    public function avvisi(): array
    {
        return [...$this->avvisi, ...$this->media->avvisi()];
    }

    public function dimenticaGliAvvisi(): void
    {
        $this->avvisi = [];
        $this->media->dimenticaGliAvvisi();
    }

    /**
     * La notizia che la redazione ha gia' scritto a mano con lo stesso slug.
     *
     * E' la seconda chiave, quella che evita il doppione quando lo stesso
     * comunicato e' stato pubblicato dal pannello: la riga esistente si
     * riscrive e prende il `wp_id`, cosi' dal giro dopo si riconosce dalla
     * prima. Senza, accanto le nascerebbe un gemello con lo slug numerato.
     *
     * Solo righe senza `wp_id`: una che ce l'ha appartiene a un altro
     * comunicato di WordPress e non va toccata.
     *
     * @param  array<string, mixed>  $comunicato
     */
    private function daAdottare(array $comunicato, int $wpId): ?Post
    {
        $slug = (string) ($comunicato['slug'] ?? '');

        if ($slug === '') {
            return null;
        }

        $notizia = Post::where('slug', $slug)->whereNull('wp_id')->first();

        if ($notizia !== null) {
            $this->avvisi[] = "notizia #{$notizia->id} ({$slug}) gia' in archivio senza wp_id: riscritta come {$wpId}.";
        }

        return $notizia;
    }

    /**
     * Lo slug da usare, unico in archivio.
     *
     * @param  array<string, mixed>  $comunicato
     */
    private function slug(array $comunicato, string $titolo, ?Post $notizia): string
    {
        $slug = (string) ($comunicato['slug'] ?? '');

        if (! $this->pulitore->slugValido($slug)) {
            // WordPress lascia l'id del post come slug quando si pubblica senza
            // titolo: `41541-2` finirebbe nell'indirizzo della notizia e nel
            // feed che legge la Lega.
            $dalTitolo = $this->pulitore->slugDalTitolo($titolo);

            if ($dalTitolo !== '') {
                $this->avvisi[] = "slug \"{$slug}\" generato da WordPress: sostituito con \"{$dalTitolo}\".";
                $slug = $dalTitolo;
            }
        }

        if ($slug === '') {
            $slug = 'comunicato-'.((int) ($comunicato['id'] ?? 0));
        }

        $base = $slug;
        $progressivo = 2;

        while (Post::where('slug', $slug)->when($notizia, fn ($q) => $q->whereKeyNot($notizia->getKey()))->exists()) {
            $slug = $base.'-'.$progressivo++;
        }

        return $slug;
    }

    /**
     * Le categorie del comunicato, tradotte negli id di casa nostra.
     *
     * @param  mixed  $wpId
     * @return list<int>
     */
    private function categorie($wpId): array
    {
        $wpId = $this->interi($wpId);
        $mancanti = array_values(array_diff($wpId, array_keys($this->categorieRisolte)));

        if ($mancanti !== []) {
            $remote = $this->lettore->categorie($mancanti);

            foreach ($mancanti as $uno) {
                $this->categorieRisolte[$uno] = isset($remote[$uno])
                    ? $this->risolviLaCategoria($remote[$uno])
                    : null;

                if (! isset($remote[$uno])) {
                    $this->avvisi[] = "categoria WordPress {$uno} non trovata: il comunicato ne fara' a meno.";
                }
            }
        }

        return array_values(array_filter(array_map(fn (int $uno): ?int => $this->categorieRisolte[$uno] ?? null, $wpId)));
    }

    /**
     * Trova la categoria in archivio, o la crea.
     *
     * Si prova `wp_id`, poi lo slug, poi il nome esatto — come la risoluzione
     * delle squadre della Lega, e per lo stesso motivo. Le due strade in fondo
     * non sono teoriche: "News Sponsor" da noi si chiama `sponsor`, e "Serie A1
     * 2026/2027" la redazione l'aveva gia' creata a mano come
     * `serie-a1-20262027`, senza `wp_id` e prima in ordine di menu. Cercando il
     * solo `wp_id` sarebbe nata una seconda categoria con lo stesso nome, e
     * nove comunicati su quindici sarebbero finiti li' dentro.
     *
     * Quando si trova per slug o per nome, l'identificativo di WordPress si
     * scrive: dal giro dopo basta il primo confronto.
     *
     * @param  array{id: int, name: string, slug: string}  $remota
     */
    private function risolviLaCategoria(array $remota): int
    {
        $nome = html_entity_decode($remota['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $slug = $remota['slug'];

        // Su WordPress i comunicati senza categoria stanno in "Senza
        // categoria": da noi quella categoria e' "Notizie", ed e' cosi' che
        // l'import di allora l'ha ribattezzata.
        if ($slug === 'senza-categoria') {
            $nome = 'Notizie';
            $slug = 'notizie';
        }

        $categoria = Category::where('wp_id', $remota['id'])->first()
            ?? Category::where('slug', $slug)->first()
            ?? $this->categoriaPerNome($nome);

        if ($categoria === null) {
            $categoria = Category::create([
                'wp_id' => $remota['id'],
                'name' => ['it' => $nome],
                'slug' => $slug,
            ]);

            $this->avvisi[] = "categoria nuova: \"{$nome}\" ({$slug}).";

            return (int) $categoria->getKey();
        }

        if ($categoria->wp_id === null) {
            $categoria->update(['wp_id' => $remota['id']]);
        }

        return (int) $categoria->getKey();
    }

    /**
     * La categoria che si chiama cosi' in italiano.
     *
     * Lo slug della redazione puo' non coincidere con quello di WordPress
     * (`serie-a1-20262027` contro `serie-a1-2026-2027`) mentre il nome sulla
     * pagina e' lo stesso.
     */
    private function categoriaPerNome(string $nome): ?Category
    {
        return Category::all()->first(
            fn (Category $categoria): bool => mb_strtolower(trim($categoria->testoTradotto('name', 'it'))) === mb_strtolower(trim($nome))
        );
    }

    /**
     * @param  mixed  $wpId
     * @return list<int>
     */
    private function etichette($wpId): array
    {
        $wpId = $this->interi($wpId);
        $mancanti = array_values(array_diff($wpId, array_keys($this->etichetteRisolte)));

        if ($mancanti !== []) {
            $remote = $this->lettore->etichette($mancanti);

            foreach ($mancanti as $uno) {
                $this->etichetteRisolte[$uno] = isset($remote[$uno])
                    ? $this->risolviLEtichetta($remote[$uno])
                    : null;
            }
        }

        return array_values(array_filter(array_map(fn (int $uno): ?int => $this->etichetteRisolte[$uno] ?? null, $wpId)));
    }

    /**
     * Le etichette si riconoscono dallo slug: in archivio nessuna delle 1626 ha
     * un `wp_id`, e `Tag` non lo scrive nemmeno.
     *
     * @param  array{id: int, name: string, slug: string}  $remota
     */
    private function risolviLEtichetta(array $remota): ?int
    {
        $nome = html_entity_decode($remota['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($this->etichettaDaScartare($nome, $remota['slug'])) {
            return null;
        }

        return (int) Tag::firstOrCreate(
            ['slug' => $remota['slug']],
            ['name' => $nome, 'slug' => $remota['slug']],
        )->getKey();
    }

    private function etichettaDaScartare(string $nome, string $slug): bool
    {
        if (in_array($slug, self::ETICHETTE_ESCLUSE, true)) {
            return true;
        }

        foreach (self::ETICHETTE_SPAZZATURA as $forma) {
            if (preg_match($forma, $nome) === 1) {
                return true;
            }
        }

        return mb_strlen(trim($nome)) < 3;
    }

    /**
     * Scarica l'immagine in evidenza e la mette nella collezione `cover`.
     *
     * E' la copertina che si vede nell'elenco delle notizie, nel feed RSS e
     * nelle anteprime social: senza, il comunicato nuovo sfigura accanto ai
     * precedenti.
     */
    private function copertina(Post $notizia, int $wpMediaId, bool $forza): void
    {
        if ($wpMediaId <= 0) {
            return;
        }

        $giaPresente = $notizia->getFirstMedia('cover');

        if ($giaPresente !== null && ! $forza) {
            return;
        }

        $indirizzo = $this->lettore->indirizzoDelMedia($wpMediaId);

        if ($indirizzo === null) {
            $this->avvisi[] = "copertina {$wpMediaId} di \"{$notizia->slug}\": il vecchio sito non la espone piu'.";

            return;
        }

        try {
            $risposta = Http::timeout(60)->retry(2, 1000, throw: false)->get($indirizzo);

            if (! $risposta->successful() || @getimagesizefromstring($risposta->body()) === false) {
                $this->avvisi[] = "copertina di \"{$notizia->slug}\": {$indirizzo} non e' un'immagine leggibile.";

                return;
            }

            if ($giaPresente !== null) {
                $notizia->clearMediaCollection('cover');
            }

            $notizia->addMediaFromString($risposta->body())
                ->usingFileName(basename((string) parse_url($indirizzo, PHP_URL_PATH)))
                ->toMediaCollection('cover');

            $this->copertineScaricate++;
        } catch (\Throwable $errore) {
            $this->avvisi[] = "copertina di \"{$notizia->slug}\": {$errore->getMessage()}";
        }
    }

    /**
     * Il campo `rendered` di WordPress, che sui campi vuoti e' una stringa.
     *
     * @param  array<string, mixed>  $comunicato
     */
    private function reso(array $comunicato, string $campo): string
    {
        $valore = $comunicato[$campo] ?? '';

        if (is_array($valore)) {
            $valore = $valore['rendered'] ?? '';
        }

        return is_string($valore) ? $valore : '';
    }

    /**
     * @param  array<string, mixed>  $comunicato
     */
    private function metaTitolo(array $comunicato): ?string
    {
        $titolo = $comunicato['yoast_head_json']['og_title'] ?? null;

        return is_string($titolo) && $titolo !== '' ? $this->pulitore->titolo($titolo) : null;
    }

    /**
     * @param  array<string, mixed>  $comunicato
     * @return array<string, string>|null
     */
    private function metaDescrizione(array $comunicato): ?array
    {
        $descrizione = $comunicato['yoast_head_json']['og_description'] ?? null;

        if (! is_string($descrizione) || $descrizione === '') {
            return null;
        }

        return ['it' => html_entity_decode($descrizione, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
    }

    /**
     * @param  mixed  $valore
     * @return list<int>
     */
    private function interi($valore): array
    {
        if (! is_array($valore)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($uno): int => is_numeric($uno) ? (int) $uno : 0, $valore),
            fn (int $uno): bool => $uno > 0,
        )));
    }
}
