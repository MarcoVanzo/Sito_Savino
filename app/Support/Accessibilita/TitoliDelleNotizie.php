<?php

namespace App\Support\Accessibilita;

/**
 * Le correzioni di accessibilità che il contenuto importato da WordPress
 * permette di fare in modo meccanico, senza decidere niente al posto della
 * redazione.
 *
 * - **Titoli saltati.** In pagina il titolo della notizia è l'`h1`; 309
 *   comunicati su 958 cominciavano da `h3` o `h4` (l'editor di WordPress li
 *   sceglieva per la dimensione, non per la struttura) e uno aveva un secondo
 *   `h1`. Chi naviga per titoli con uno screen reader trovava un buco nella
 *   gerarchia (WCAG 1.3.1). Tutti i titoli del testo scalano dello stesso
 *   numero di livelli perché il più alto diventi `h2`: la gerarchia interna
 *   resta quella scritta.
 * - **`aria-level` sui paragrafi.** Dieci comunicati hanno `<p aria-level="4">`,
 *   un residuo dell'editor: `aria-level` su un paragrafo non è ammesso e axe lo
 *   segnala come violazione grave (aria-allowed-attr). Si toglie l'attributo.
 *
 * Le immagini senza testo alternativo non si toccano: l'alt lo scrive una
 * persona guardando l'immagine. Quelle dell'archivio importato (25, in 21
 * notizie) sono state descritte con la migrazione
 * `2026_09_26_170000_testi_alternativi_delle_immagini_delle_notizie`; il
 * comando elenca quelle che restano, per esempio in un comunicato nuovo.
 *
 * Tutto è idempotente: dopo la prima passata il titolo più alto è già `h2` e
 * non restano `aria-level`.
 */
class TitoliDelleNotizie
{
    /**
     * @return array{html: string, scarto: int, aria_level: int, immagini_senza_testo: int}
     */
    public static function correggi(string $html): array
    {
        $ariaLevel = 0;
        $html = preg_replace_callback(
            '/<(?!h[1-6]\b)([a-z][a-z0-9]*)\b([^>]*?)\s+aria-level=("|\')\d+\3([^>]*)>/i',
            function (array $m) use (&$ariaLevel): string {
                // Un elemento con role="heading" l'aria-level lo vuole: resta.
                if (preg_match('/\brole=("|\')heading\1/i', $m[2].$m[4])) {
                    return $m[0];
                }
                $ariaLevel++;

                return '<'.$m[1].$m[2].$m[4].'>';
            },
            $html,
        ) ?? $html;

        $scarto = 0;
        if (preg_match_all('/<h([1-6])\b/i', $html, $livelli)) {
            $scarto = 2 - min(array_map('intval', $livelli[1]));
        }

        if ($scarto !== 0) {
            $html = preg_replace_callback(
                '/<(\/?)h([1-6])\b/i',
                fn (array $m): string => '<'.$m[1].'h'.max(2, min(6, (int) $m[2] + $scarto)),
                $html,
            ) ?? $html;
        }

        return [
            'html' => $html,
            'scarto' => $scarto,
            'aria_level' => $ariaLevel,
            // alt vuoto o assente: per uno screen reader, un'immagine muta.
            'immagini_senza_testo' => count(array_filter(
                preg_match_all('/<img\b[^>]*>/i', $html, $tag) ? $tag[0] : [],
                fn (string $img): bool => ! preg_match('/\balt=("|\')\s*[^"\'\s]/i', $img),
            )),
        ];
    }
}
