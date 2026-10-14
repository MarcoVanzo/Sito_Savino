<?php

namespace App\Support;

use App\Models\MenuItem;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Segue i link interni del sito e dice dove portano davvero.
 *
 * Un link del menu o della home che risponde 301/302 funziona per chi clicca,
 * ma costa un giro in più e Google lo conta come un link interno a un rimando:
 * l'audit SEO di ottobre 2026 ne ha trovati una ventina (`/ticketing`,
 * `/youth/u17`, `/shop/kit-gara`…). Le rotte di rimando restano per i vecchi
 * link esterni; dal sito stesso si punta all'indirizzo finale.
 *
 * Le richieste passano dal kernel HTTP dell'applicazione, non dalla rete: il
 * controllo gira uguale in un test, in locale e dalla console di produzione.
 * Le usano `link:verifica` e `LinkInterniSenzaRimandiTest`.
 */
final class SeguiILinkInterni
{
    /**
     * Percorsi che non sono pagine: file, asset, endpoint del pannello.
     */
    private const PERCORSI_ESCLUSI = '#^/(build|storage|images|fonts|vendor|livewire|filament|admin|api|_debugbar)(/|$)#';

    private const ESTENSIONI_ESCLUSE = '#\.(jpe?g|png|webp|gif|svg|ico|pdf|mp4|webm|css|js|map|woff2?|ttf|json|txt)$#i';

    /**
     * Le props Inertia con un indirizzo: `href`, `url`, `link` e le chiavi che
     * finiscono cosi' (`cta_url`, `tip_link_url`, `alternateUrl`).
     */
    private const CHIAVI_INDIRIZZO = '/(^|_)(href|url|link)$|(Href|Url|Link)$/';

    /**
     * I link delle voci di menu (testata e footer) in tutte le lingue, cosi'
     * come arrivano al frontend da MenuItem::getTree().
     *
     * @return list<array{origine: string, voce: string, href: string}>
     */
    public static function delMenu(): array
    {
        $linguaDiPrima = app()->getLocale();
        $link = [];

        foreach (config('app.supported_locales', ['it']) as $lingua) {
            app()->setLocale($lingua);

            foreach (['main' => 'menu', 'footer' => 'footer'] as $posizione => $nome) {
                foreach (MenuItem::getTree($posizione) as $voce) {
                    foreach ([$voce, ...($voce['children'] ?? [])] as $nodo) {
                        $link[] = [
                            'origine' => $nome.' ('.$lingua.')',
                            'voce' => self::etichetta($nodo['label'] ?? ''),
                            'href' => (string) ($nodo['href'] ?? ''),
                        ];
                    }
                }
            }
        }

        app()->setLocale($linguaDiPrima);

        return $link;
    }

    /**
     * I link interni di una pagina: gli href dell'HTML e gli indirizzi nelle
     * props Inertia (menu, footer, CTA, slide, notizie…).
     *
     * @return list<array{origine: string, voce: string, href: string}>
     */
    public static function dellaPagina(string $percorso): array
    {
        $html = (string) self::richiesta($percorso)->getContent();
        $indirizzi = [];

        if (preg_match_all('/\bhref="([^"]*)"/i', $html, $trovati)) {
            foreach ($trovati[1] as $href) {
                $indirizzi[] = html_entity_decode($href, ENT_QUOTES | ENT_HTML5);
            }
        }

        $props = self::propsInertia($html);
        unset($props['ziggy']);
        self::raccogliGliIndirizzi($props, $indirizzi);

        return collect($indirizzi)
            ->map(fn (string $href) => self::percorsoInterno($href))
            ->filter()
            ->unique()
            ->map(fn (string $href) => ['origine' => 'pagina '.$percorso, 'voce' => '', 'href' => $href])
            ->values()
            ->all();
    }

    /**
     * I link che non arrivano a una pagina: rimandi (3xx), pagine inesistenti
     * (404) ed errori. Ogni indirizzo si chiede una volta sola; vale la prima
     * origine in cui compare.
     *
     * @param  list<array{origine: string, voce: string, href: string}>  $link
     * @return list<array{origine: string, voce: string, href: string, stato: int, destinazione: string, problema: string}>
     */
    public static function problemi(array $link): array
    {
        $esiti = [];
        $problemi = [];

        foreach ($link as $voce) {
            $percorso = self::percorsoInterno($voce['href']);

            if ($percorso === null || isset($esiti[$percorso])) {
                continue;
            }

            $risposta = self::richiesta($percorso);
            $stato = $risposta->getStatusCode();
            $esiti[$percorso] = $stato;

            $problema = match (true) {
                $stato >= 300 && $stato < 400 => 'rimando',
                $stato === 404 => 'pagina inesistente',
                $stato >= 400 => 'errore',
                default => null,
            };

            if ($problema === null) {
                continue;
            }

            $destinazione = (string) $risposta->headers->get('Location', '');

            $problemi[] = [
                ...$voce,
                'href' => $percorso,
                'stato' => $stato,
                'destinazione' => self::percorsoInterno($destinazione) ?? $destinazione,
                'problema' => $problema,
            ];
        }

        return $problemi;
    }

    /**
     * Il percorso (con query) di un indirizzo del sito, o null se porta fuori,
     * e' un file o non e' un indirizzo (`mailto:`, `#`, `documento:`).
     */
    public static function percorsoInterno(string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            $host = parse_url($href, PHP_URL_HOST);

            if ($host === null || $host !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
                return null;
            }

            $href = (string) preg_replace('#^https?://[^/]+#i', '', $href) ?: '/';
        }

        if (! str_starts_with($href, '/') || str_starts_with($href, '//')) {
            return null;
        }

        $href = (string) strtok($href, '#');
        $percorso = (string) parse_url($href, PHP_URL_PATH);

        if (preg_match(self::PERCORSI_ESCLUSI, $percorso) || preg_match(self::ESTENSIONI_ESCLUSE, $percorso)) {
            return null;
        }

        return $href;
    }

    /**
     * Una GET passata dal kernel HTTP, sull'host di APP_URL: con un altro host
     * TrustHosts e PortaSullIndirizzoDelSito risponderebbero loro.
     */
    private static function richiesta(string $percorso): Response
    {
        $richiesta = Request::create(rtrim((string) config('app.url'), '/').$percorso, 'GET', server: [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        return app(Kernel::class)->handle($richiesta);
    }

    /**
     * @return array<string, mixed>
     */
    private static function propsInertia(string $html): array
    {
        // Prima lo script: il suo attributo `data-page="app"` corrisponderebbe
        // anche alla forma vecchia, quella con il JSON nell'attributo.
        if (preg_match('#<script[^>]*data-page="app"[^>]*>(.*?)</script>#s', $html, $script)) {
            $json = $script[1];
        } elseif (preg_match('/data-page="([^"]*)"/', $html, $attributo)) {
            $json = html_entity_decode($attributo[1], ENT_QUOTES | ENT_HTML5);
        } else {
            return [];
        }

        $pagina = json_decode($json, true);

        return is_array($pagina['props'] ?? null) ? $pagina['props'] : [];
    }

    /**
     * @param  array<array-key, mixed>  $valori
     * @param  list<string>  $indirizzi
     */
    private static function raccogliGliIndirizzi(array $valori, array &$indirizzi): void
    {
        foreach ($valori as $chiave => $valore) {
            if (is_array($valore)) {
                self::raccogliGliIndirizzi($valore, $indirizzi);
            } elseif (is_string($valore) && is_string($chiave) && preg_match(self::CHIAVI_INDIRIZZO, $chiave)) {
                $indirizzi[] = $valore;
            }
        }
    }

    private static function etichetta(mixed $label): string
    {
        return is_array($label) ? (string) ($label[app()->getLocale()] ?? reset($label)) : (string) $label;
    }
}
