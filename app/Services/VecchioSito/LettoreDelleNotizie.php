<?php

namespace App\Services\VecchioSito;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Legge i comunicati dalle API REST di WordPress del vecchio sito.
 *
 * Le 941 notizie dell'archivio erano entrate da un export statico salvato sul
 * portatile (`~/wp_export_savino/data/*.json`). Quell'export e' fermo al 2
 * luglio 2026 e i suoi file non ci sono piu': ripetere l'import di allora oggi
 * non porterebbe niente di nuovo. WordPress pero' espone `wp-json` senza
 * chiave, e da li' i comunicati si rileggono quando si vuole — il che rende
 * questo import ripetibile, che l'altro non era.
 *
 * Vale finche' il vecchio sito risponde: il giorno in cui il dominio passa al
 * sito nuovo questa strada si chiude, e con essa l'ultima copia di quei testi.
 */
class LettoreDelleNotizie
{
    /** Il massimo che WordPress concede in una pagina. */
    private const PER_PAGINA = 100;

    /** Rete di sicurezza: l'archivio intero sono dieci pagine, non cento. */
    private const PAGINE_MASSIME = 50;

    public function __construct(
        private readonly ?string $base = null,
    ) {}

    /**
     * I comunicati pubblicati dopo un certo istante, dal piu' vecchio al piu'
     * recente.
     *
     * L'ordine e' voluto: importando in ordine di pubblicazione, un'
     * interruzione a meta' lascia un archivio senza buchi, e ripartire dalla
     * data dell'ultimo importato riprende esattamente da li'.
     *
     * @return list<array<string, mixed>>
     */
    public function comunicatiDopo(\DateTimeInterface $istante, ?int $massimo = null): array
    {
        $comunicati = [];

        for ($pagina = 1; $pagina <= self::PAGINE_MASSIME; $pagina++) {
            $blocco = $this->chiedi('posts', [
                'per_page' => self::PER_PAGINA,
                'page' => $pagina,
                'orderby' => 'date',
                'order' => 'asc',
                'status' => 'publish',
                // `after` si confronta con `post_date`, l'ora locale del
                // vecchio sito: passare GMT rifarebbe entrare a ogni giro le
                // due ore gia' importate.
                'after' => $this->localeDelVecchioSito($istante)->format('Y-m-d\TH:i:s'),
                '_fields' => 'id,date,slug,status,title,content,excerpt,categories,tags,featured_media,yoast_head_json.og_title,yoast_head_json.og_description',
            ]);

            foreach ($blocco as $comunicato) {
                $comunicati[] = $comunicato;

                if ($massimo !== null && count($comunicati) >= $massimo) {
                    return $comunicati;
                }
            }

            if (count($blocco) < self::PER_PAGINA) {
                break;
            }
        }

        return $comunicati;
    }

    /**
     * Le categorie con questi identificativi.
     *
     * @param  list<int>  $id
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    public function categorie(array $id): array
    {
        return $this->tassonomia('categories', $id);
    }

    /**
     * @param  list<int>  $id
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    public function etichette(array $id): array
    {
        return $this->tassonomia('tags', $id);
    }

    /**
     * L'indirizzo del file di un allegato, o null se non c'e' piu'.
     */
    public function indirizzoDelMedia(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $risposta = $this->richiesta()->get($this->indirizzo('media/'.$id), ['_fields' => 'source_url']);
        } catch (\Throwable) {
            return null;
        }

        if (! $risposta->successful()) {
            return null;
        }

        $indirizzo = $risposta->json('source_url');

        return is_string($indirizzo) && $indirizzo !== '' ? $indirizzo : null;
    }

    /**
     * Categorie ed etichette si chiedono a blocchi: `include` accetta un elenco
     * di identificativi, e una notizia ne cita fino a trenta.
     *
     * @param  list<int>  $id
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    private function tassonomia(string $risorsa, array $id): array
    {
        $id = array_values(array_unique(array_filter($id, fn (int $uno): bool => $uno > 0)));

        if ($id === []) {
            return [];
        }

        $trovate = [];

        foreach (array_chunk($id, self::PER_PAGINA) as $blocco) {
            $righe = $this->chiedi($risorsa, [
                'include' => implode(',', $blocco),
                'per_page' => self::PER_PAGINA,
                '_fields' => 'id,name,slug',
            ]);

            foreach ($righe as $riga) {
                if (isset($riga['id'], $riga['name'], $riga['slug'])) {
                    $trovate[(int) $riga['id']] = [
                        'id' => (int) $riga['id'],
                        'name' => (string) $riga['name'],
                        'slug' => (string) $riga['slug'],
                    ];
                }
            }
        }

        return $trovate;
    }

    /**
     * @param  array<string, mixed>  $parametri
     * @return list<array<string, mixed>>
     */
    private function chiedi(string $risorsa, array $parametri): array
    {
        $risposta = $this->richiesta()->get($this->indirizzo($risorsa), $parametri);

        // Chiedere una pagina oltre l'ultima e' un 400, non una lista vuota:
        // ci si arriva solo quando il totale e' un multiplo esatto di cento.
        if ($risposta->status() === 400) {
            return [];
        }

        if (! $risposta->successful()) {
            throw new VecchioSitoNonRisponde(
                "Il vecchio sito ha risposto HTTP {$risposta->status()} su /{$risorsa}."
            );
        }

        $righe = $risposta->json();

        if (! is_array($righe)) {
            throw new VecchioSitoNonRisponde("Risposta illeggibile del vecchio sito su /{$risorsa}.");
        }

        return array_values(array_filter($righe, 'is_array'));
    }

    private function richiesta(): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => (string) config('services.vecchio_sito.user_agent')])
            ->timeout((int) config('services.vecchio_sito.timeout', 30))
            ->retry(2, 1000, throw: false)
            ->acceptJson();
    }

    private function indirizzo(string $risorsa): string
    {
        $base = rtrim($this->base ?? (string) config('services.vecchio_sito.base_url'), '/');

        return $base.'/wp-json/wp/v2/'.$risorsa;
    }

    /**
     * L'istante nel fuso con cui WordPress ragiona.
     *
     * Le API confrontano `after` con `post_date`, che e' l'ora locale del
     * vecchio sito — ed e' anche quella che l'import di allora ha scritto in
     * `posts.published_at` (il comunicato delle 14:00 e' in archivio alle
     * 14:00, non alle 12:00). Oggi i due fusi coincidono, `Europe/Rome` di qua
     * e di la', e la conversione non sposta niente: serve a non sbagliare il
     * giorno in cui uno dei due cambia.
     */
    private function localeDelVecchioSito(\DateTimeInterface $istante): \DateTimeImmutable
    {
        $fuso = (string) config('services.vecchio_sito.fuso_orario', 'Europe/Rome');

        return \DateTimeImmutable::createFromInterface($istante)
            ->setTimezone(new \DateTimeZone($fuso));
    }
}
