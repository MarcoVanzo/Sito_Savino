<?php

namespace App\Services;

/**
 * Pulisce il contenuto HTML esportato da WordPress.
 *
 * Rimuove stili inline, attributi deprecati, commenti,
 * e converte entità HTML in caratteri UTF-8.
 */
class WordPressContentCleaner
{
    /**
     * Lista degli slug dei post importati (per riscrittura link).
     * Popolata dal comando di importazione.
     */
    private array $importedSlugs = [];

    /**
     * Dominio del vecchio sito WordPress.
     */
    private string $oldDomain = 'savinodelbenevolley.it';

    public function setImportedSlugs(array $slugs): self
    {
        $this->importedSlugs = $slugs;

        return $this;
    }

    /**
     * Esegue la pipeline completa di pulizia su un contenuto HTML.
     */
    public function clean(string $html): string
    {
        $html = $this->removeHtmlComments($html);
        $html = $this->removeSpanWrappers($html);
        $html = $this->removeAlignAttributes($html);
        $html = $this->removeInlineStyles($html);
        $html = $this->removeWpClasses($html);
        $html = $this->rewriteInternalLinks($html);
        $html = $this->decodeHtmlEntities($html);
        $html = $this->cleanupWhitespace($html);

        return trim($html);
    }

    /**
     * Pulisce un titolo: decodifica entità HTML e rimuove tag.
     */
    public function cleanTitle(string $title): string
    {
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = strip_tags($title);

        return trim($title);
    }

    /**
     * Pulisce un excerpt WordPress:
     * - Rimuove commenti AddThis
     * - Rimuove [&hellip;] e varianti
     * - Decodifica entità HTML
     * - Strip tag HTML
     * - Tronca a ~250 caratteri alla frase completa più vicina.
     */
    public function cleanExcerpt(string $excerpt, ?string $contentFallback = null): string
    {
        // Rimuovi commenti HTML (AddThis, ecc.)
        $excerpt = preg_replace('/<!--.*?-->/s', '', $excerpt);

        // Rimuovi [&hellip;] e varianti
        $excerpt = preg_replace('/\[&hellip;\]/', '…', $excerpt);
        $excerpt = preg_replace('/\[&#8230;\]/', '…', $excerpt);
        $excerpt = preg_replace('/\[\x{2026}\]/u', '…', $excerpt);

        // Decodifica entità e strip tag
        $excerpt = html_entity_decode($excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $excerpt = strip_tags($excerpt);
        $excerpt = trim($excerpt);

        // Se l'excerpt è troppo corto, usiamo il contenuto come fallback
        if (mb_strlen($excerpt) < 50 && $contentFallback) {
            $clean = strip_tags(html_entity_decode($contentFallback, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $clean = preg_replace('/\s+/', ' ', $clean);
            $excerpt = trim($clean);
        }

        // Tronca a ~250 caratteri alla frase completa più vicina
        if (mb_strlen($excerpt) > 280) {
            $truncated = mb_substr($excerpt, 0, 280);
            // Trova l'ultimo punto, punto esclamativo o punto interrogativo
            $lastSentenceEnd = max(
                (int) mb_strrpos($truncated, '. '),
                (int) mb_strrpos($truncated, '! '),
                (int) mb_strrpos($truncated, '? '),
                (int) mb_strrpos($truncated, ".\n"),
            );

            if ($lastSentenceEnd > 100) {
                $excerpt = mb_substr($excerpt, 0, $lastSentenceEnd + 1);
            } else {
                // Fallback: tronca all'ultimo spazio e aggiungi puntini
                $lastSpace = mb_strrpos($truncated, ' ');
                $excerpt = mb_substr($excerpt, 0, $lastSpace ?: 250).'…';
            }
        }

        return trim($excerpt);
    }

    /**
     * Genera uno slug dal titolo quando quello originale è invalido.
     */
    public function generateSlug(string $title): string
    {
        $slug = mb_strtolower($title);
        // Rimuovi accenti comuni italiani
        $slug = strtr($slug, [
            'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i',
            'ò' => 'o', 'ù' => 'u', 'ć' => 'c', 'č' => 'c',
            'ž' => 'z', 'š' => 's', 'đ' => 'dj', 'ñ' => 'n',
        ]);
        // Sostituisci tutto ciò che non è alfanumerico con trattino
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Limita la lunghezza
        if (mb_strlen($slug) > 80) {
            $slug = mb_substr($slug, 0, 80);
            $slug = rtrim($slug, '-');
        }

        return $slug;
    }

    /**
     * Verifica se uno slug è valido (non auto-generato da WP).
     */
    public function isValidSlug(string $slug): bool
    {
        // Slug che sono solo numeri o tipo "41287-2"
        if (preg_match('/^\d+(-\d+)?$/', $slug)) {
            return false;
        }

        // Slug troppo corti
        if (mb_strlen($slug) < 5) {
            return false;
        }

        return true;
    }

    /**
     * Rimuove commenti HTML (Gutenberg, AddThis, ecc.).
     */
    private function removeHtmlComments(string $html): string
    {
        return preg_replace('/<!--.*?-->/s', '', $html);
    }

    /**
     * Rimuove i tag <span> wrapper con stili inline (font-family, color, font-size),
     * mantenendo il testo interno.
     *
     * Processa iterativamente per gestire nidificazioni profonde:
     *   <span style="color: ..."><span style="font-family: ...">testo</span></span>
     *   → testo
     */
    private function removeSpanWrappers(string $html): string
    {
        $previous = '';
        $maxIterations = 10; // Sicurezza anti-loop infinito

        while ($html !== $previous && $maxIterations-- > 0) {
            $previous = $html;
            // Rimuove <span> con solo attributi style contenenti font/color/size
            $html = preg_replace(
                '/<span\s+style="[^"]*(?:font-family|color|font-size|font-weight|font-style)[^"]*"\s*>(.*?)<\/span>/is',
                '$1',
                $html
            );
        }

        // Rimuove anche <span> senza attributi (wrapper vuoti residui)
        $previous = '';
        $maxIterations = 5;
        while ($html !== $previous && $maxIterations-- > 0) {
            $previous = $html;
            $html = preg_replace('/<span\s*>(.*?)<\/span>/is', '$1', $html);
        }

        return $html;
    }

    /**
     * Rimuove attributi align="..." dai tag HTML.
     */
    private function removeAlignAttributes(string $html): string
    {
        return preg_replace('/\s+align="[^"]*"/i', '', $html);
    }

    /**
     * Rimuove attributi style="..." residui dai tag HTML.
     */
    private function removeInlineStyles(string $html): string
    {
        return preg_replace('/\s+style="[^"]*"/i', '', $html);
    }

    /**
     * Rimuove classi CSS WordPress-specifiche (wp-*, is-*, has-*).
     * Se il class attribute diventa vuoto, lo rimuove del tutto.
     */
    private function removeWpClasses(string $html): string
    {
        // Rimuove classi WP da attributi class
        $html = preg_replace_callback(
            '/\s+class="([^"]*)"/i',
            function ($match) {
                $classes = preg_split('/\s+/', $match[1]);
                $filtered = array_filter($classes, function ($class) {
                    return ! preg_match('/^(wp-|is-|has-)/', $class);
                });

                if (empty($filtered)) {
                    return ''; // Rimuove l'attributo class del tutto
                }

                return ' class="'.implode(' ', $filtered).'"';
            },
            $html
        );

        return $html;
    }

    /**
     * Riscrive i link interni dal vecchio sito al nuovo formato.
     *
     * - Link a post importati → /news/{slug}
     * - Link a post non importati → rimuove il tag <a>, mantiene il testo
     * - URL di immagini/PDF da wp-content → lascia invariati (gestiti separatamente)
     */
    private function rewriteInternalLinks(string $html): string
    {
        $oldDomain = preg_quote($this->oldDomain, '/');

        $html = preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']https?:\/\/'.$oldDomain.'\/([^"\']*?)["\']([^>]*?)>(.*?)<\/a>/is',
            function ($match) {
                $beforeAttrs = $match[1];
                $path = rtrim($match[2], '/');
                $afterAttrs = $match[3];
                $linkText = $match[4];

                // Ignora link a wp-content (immagini, PDF, ecc.)
                if (str_contains($path, 'wp-content/')) {
                    return $match[0]; // Lascia invariato
                }

                // Verifica se lo slug corrisponde a un post importato
                if (in_array($path, $this->importedSlugs)) {
                    return '<a '.$beforeAttrs.'href="/news/'.$path.'"'.$afterAttrs.'>'.$linkText.'</a>';
                }

                // Post non importato: rimuove il link, mantiene il testo
                return $linkText;
            },
            $html
        );

        return $html;
    }

    /**
     * Decodifica entità HTML in caratteri UTF-8.
     */
    private function decodeHtmlEntities(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Pulisce whitespace e tag vuoti.
     */
    private function cleanupWhitespace(string $html): string
    {
        // Rimuove <p> vuoti
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $html);

        // Rimuove <b> e <strong> vuoti
        $html = preg_replace('/<(b|strong)>\s*<\/\1>/i', '', $html);

        // Normalizza newline multipli
        $html = preg_replace('/(\s*\n){3,}/', "\n\n", $html);

        return $html;
    }
}
