<?php

namespace App\Services\Affiliazioni;

use App\Enums\AffiliateTier;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Legge la pagina "Progetto Affiliazioni" del sito precedente.
 *
 * La pagina non ha API: i loghi stanno in sezioni introdotte da un titolo
 * ("Main partner", "Partner Ufficiale", "Societa' Affiliate") che e' il
 * livello. Si scorre il documento in ordine tenendo memoria dell'ultimo
 * titolo incontrato, come fa l'import degli sponsor.
 *
 * Solo le immagini con un `alt` sono loghi di societa': senza quel filtro
 * entrerebbero in elenco il marchio in testata, i riquadri del menu e i pixel
 * di tracciamento.
 */
class ParserDelleAffiliate
{
    /** Titolo di sezione della pagina d'origine => livello. */
    private const LIVELLO_PER_TITOLO = [
        'main partner' => AffiliateTier::Main,
        'partner ufficiale' => AffiliateTier::Official,
        'partner ufficiali' => AffiliateTier::Official,
        'societa affiliate' => AffiliateTier::Affiliated,
        'societa affiliata' => AffiliateTier::Affiliated,
    ];

    /**
     * @return list<array{name: string, tier: string, url: ?string, logo: string}>
     */
    public function analizza(string $html, string $urlDiOrigine): array
    {
        $documento = new DOMDocument;
        $precedente = libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($precedente);

        $xpath = new DOMXPath($documento);
        $nodi = $xpath->query('//h1|//h2|//h3|//h4|//img');

        $societa = [];
        $livello = null;
        $viste = [];

        foreach ($nodi ?: [] as $nodo) {
            if (! $nodo instanceof DOMElement) {
                continue;
            }

            if ($nodo->tagName !== 'img') {
                $livello = self::LIVELLO_PER_TITOLO[$this->chiaveDelTitolo($nodo->textContent)] ?? $livello;

                continue;
            }

            if (! $livello instanceof AffiliateTier) {
                continue;
            }

            $logo = $this->indirizzoAssoluto($nodo->getAttribute('src'), $urlDiOrigine);
            $nome = $this->nomePulito($nodo->getAttribute('alt'));

            if ($nome === '' || $logo === null || isset($viste[Str::lower($nome)])) {
                continue;
            }

            $viste[Str::lower($nome)] = true;
            $societa[] = [
                'name' => $nome,
                'tier' => $livello->value,
                'url' => $this->sitoDellaSocieta($nodo),
                'logo' => $logo,
            ];
        }

        return $societa;
    }

    /**
     * Il titolo ridotto alla forma con cui e' scritto nella tabella: minuscolo,
     * senza accenti e senza apostrofi ("Societa' Affiliate" e "SOCIETÀ
     * AFFILIATE" sono lo stesso titolo).
     */
    private function chiaveDelTitolo(string $testo): string
    {
        $testo = Str::lower(trim(preg_replace('/\s+/u', ' ', $testo) ?? ''));
        $testo = Str::ascii($testo);

        return trim(str_replace("'", '', $testo));
    }

    /**
     * Il sito della societa' e' l'ancora che avvolge il logo.
     */
    private function sitoDellaSocieta(DOMElement $img): ?string
    {
        for ($nodo = $img->parentNode; $nodo instanceof DOMElement; $nodo = $nodo->parentNode) {
            if ($nodo->tagName !== 'a') {
                continue;
            }

            $href = trim($nodo->getAttribute('href'));

            return Str::startsWith($href, ['http://', 'https://']) ? $href : null;
        }

        return null;
    }

    private function nomePulito(string $alt): string
    {
        $nome = trim(html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $nome) ?? ''), 255, '');
    }

    private function indirizzoAssoluto(string $src, string $urlDiOrigine): ?string
    {
        $src = trim($src);

        if ($src === '' || Str::startsWith($src, 'data:')) {
            return null;
        }

        if (Str::startsWith($src, ['http://', 'https://'])) {
            return $src;
        }

        $base = parse_url($urlDiOrigine);

        return ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '').'/'.ltrim($src, '/');
    }
}
