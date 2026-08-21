<?php

namespace App\Services\GalleryLegacy;

use Illuminate\Support\Facades\Http;

/**
 * Legge gli album fotografici dal vecchio sito.
 *
 * La sezione Gallery di savinodelbenevolley.it è un tipo di contenuto
 * personalizzato di WordPress e non è esposta dalle API REST (`wp-json` conosce
 * solo post, pagine e allegati): l'unica strada è leggere le pagine pubbliche,
 * che `robots.txt` consente.
 *
 * Ogni album mostra le foto come miniature 150x150; l'originale ha lo stesso
 * indirizzo senza quel suffisso.
 */
class LettoreGalleryVecchioSito
{
    private const BASE = 'https://savinodelbenevolley.it/gallery/';

    /** Oltre questa soglia si smette di cercare: l'archivio ha 38 pagine. */
    private const PAGINE_MASSIME = 60;

    /**
     * Gli indirizzi degli album, dal più recente al più vecchio.
     *
     * @return list<string>
     */
    public function elencoAlbum(?int $massimo = null): array
    {
        $slug = [];

        for ($pagina = 1; $pagina <= self::PAGINE_MASSIME; $pagina++) {
            $html = $this->scarica($pagina === 1 ? self::BASE : self::BASE.'page/'.$pagina.'/');

            if ($html === null) {
                break;
            }

            $trovati = $this->slugNellaPagina($html);

            // Una pagina senza album (o che ripete la precedente) è la fine.
            $nuovi = array_values(array_diff($trovati, $slug));

            if ($nuovi === []) {
                break;
            }

            foreach ($nuovi as $uno) {
                $slug[] = $uno;

                if ($massimo !== null && count($slug) >= $massimo) {
                    return $slug;
                }
            }
        }

        return $slug;
    }

    /**
     * Titolo, data e foto di un album.
     *
     * @return array{slug: string, titolo: string, data: ?string, foto: list<string>}|null
     */
    public function album(string $slug): ?array
    {
        $html = $this->scarica(self::BASE.$slug.'/');

        if ($html === null) {
            return null;
        }

        $foto = $this->fotoNellAlbum($html);

        if ($foto === []) {
            return null;
        }

        return [
            'slug' => $slug,
            'titolo' => $this->titolo($html) ?? $this->titoloDalloSlug($slug),
            'data' => $this->data($html),
            'foto' => $foto,
        ];
    }

    /**
     * @return list<string>
     */
    private function slugNellaPagina(string $html): array
    {
        preg_match_all(
            '#href="https://savinodelbenevolley\.it/gallery/([a-z0-9][a-z0-9\-]*)/"#i',
            $html,
            $trovati
        );

        // `feed` è il flusso RSS, `page` la paginazione: non sono album.
        return array_values(array_unique(array_filter(
            $trovati[1],
            fn ($slug) => ! in_array($slug, ['feed', 'page'], true)
        )));
    }

    /**
     * @return list<string>
     */
    private function fotoNellAlbum(string $html): array
    {
        preg_match_all(
            '#src="(https://savinodelbenevolley\.it/wp-content/uploads/\d{4}/\d{2}/[^"]+?)-150x150\.(jpe?g|png)"#i',
            $html,
            $trovati,
            PREG_SET_ORDER
        );

        $foto = [];

        foreach ($trovati as $uno) {
            $indirizzo = $uno[1].'.'.$uno[2];

            if (! in_array($indirizzo, $foto, true)) {
                $foto[] = $indirizzo;
            }
        }

        return $foto;
    }

    private function titolo(string $html): ?string
    {
        if (! preg_match('#<title>(.*?)</title>#is', $html, $trovato)) {
            return null;
        }

        $titolo = html_entity_decode(trim($trovato[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // "GIORNATA 1 | Savino Del Bene Volley Scandicci"
        $titolo = trim(explode('|', $titolo)[0]);

        return $titolo === '' ? null : mb_convert_case($titolo, MB_CASE_TITLE, 'UTF-8');
    }

    private function data(string $html): ?string
    {
        foreach (['#"datePublished":"([^"]+)"#', '#published_time"\s+content="([^"]+)"#'] as $schema) {
            if (preg_match($schema, $html, $trovato)) {
                return substr($trovato[1], 0, 10);
            }
        }

        return null;
    }

    private function titoloDalloSlug(string $slug): string
    {
        return mb_convert_case(str_replace('-', ' ', $slug), MB_CASE_TITLE, 'UTF-8');
    }

    private function scarica(string $indirizzo): ?string
    {
        $risposta = Http::timeout(30)->retry(2, 1000, throw: false)->get($indirizzo);

        return $risposta->successful() ? $risposta->body() : null;
    }
}
