<?php

namespace App\Services\Lvf;

use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Wrapper minimo su DOMDocument per le pagine della Lega.
 *
 * Il markup è HTML5 generato da WordPress e contiene tag non conformi: si
 * silenziano gli errori di libxml, altrimenti ogni pagina emetterebbe centinaia
 * di warning senza che questo indichi un problema reale di parsing.
 */
class LvfDocument
{
    private function __construct(public readonly DOMXPath $xpath) {}

    public static function fromHtml(string $html): self
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        // Il prefisso forza libxml a interpretare il documento come UTF-8:
        // senza, gli accenti dei nomi squadra vengono mangiati.
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new self(new DOMXPath($document));
    }

    /**
     * Testo di un nodo con spazi normalizzati.
     */
    public static function text(?DOMNode $node): string
    {
        if (! $node instanceof DOMNode) {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }

    /**
     * Estrae l'identificativo numerico del club da un link.
     *
     * La Lega usa due forme per lo stesso club: `/club/club/710955/` nel
     * calendario e `/club/nome-squadra/710955/` in classifica.
     */
    public static function clubIdFromHref(string $href): ?int
    {
        return preg_match('#/club/[^/]+/(\d+)/#', $href, $m) === 1
            ? (int) $m[1]
            : null;
    }
}
