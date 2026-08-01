<?php

namespace App\Http\Middleware;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Intercetta i crawler social (Facebook, Twitter, WhatsApp, Telegram, LinkedIn)
 * e serve loro una pagina HTML minimale con i meta tag og: corretti.
 *
 * Questo sostituisce SSR per le anteprime social a costo zero.
 */
class ServeSocialCrawlerMeta
{
    /**
     * User-agent patterns dei crawler social.
     */
    private const CRAWLER_PATTERNS = [
        'facebookexternalhit',
        'Facebot',
        'Twitterbot',
        'LinkedInBot',
        'WhatsApp',
        'TelegramBot',
        'Slackbot',
        'Discordbot',
        'Pinterest',
        'vkShare',
        'Viber',
    ];

    /**
     * Meta tag statici per le pagine principali.
     * Chiave = path (senza leading slash), valore = [title, description].
     */
    private const PAGE_META = [
        '' => [
            'title' => 'Savino Del Bene Volley — Sito Ufficiale',
            'description' => 'Sito ufficiale della Savino Del Bene Volley. Scopri il roster, il calendario e i risultati della Serie A1 femminile di Scandicci.',
        ],
        'stagione' => [
            'title' => 'Stagione — Savino Del Bene Volley',
            'description' => 'Roster, staff tecnico e medico della stagione corrente della Savino Del Bene Volley.',
        ],
        'risultati' => [
            'title' => 'Risultati — Savino Del Bene Volley',
            'description' => 'Calendario e risultati delle partite della Savino Del Bene Volley.',
        ],
        'gallery' => [
            'title' => 'Gallery — Savino Del Bene Volley',
            'description' => 'Galleria fotografica ufficiale della Savino Del Bene Volley.',
        ],
        'staff' => [
            'title' => 'Staff — Savino Del Bene Volley',
            'description' => 'Staff tecnico e medico della Savino Del Bene Volley.',
        ],
        'sponsor' => [
            'title' => 'Sponsor — Savino Del Bene Volley',
            'description' => 'I partner e sponsor ufficiali della Savino Del Bene Volley.',
        ],
        'shop' => [
            'title' => 'Shop Ufficiale — Savino Del Bene Volley',
            'description' => 'Acquista maglie, merchandise e accessori ufficiali della Savino Del Bene Volley.',
        ],
        'news' => [
            'title' => 'News — Savino Del Bene Volley',
            'description' => 'Tutte le ultime notizie dalla Savino Del Bene Volley.',
        ],
        'contatti' => [
            'title' => 'Contatti — Savino Del Bene Volley',
            'description' => 'Contatta la Savino Del Bene Volley. Informazioni, sede e recapiti.',
        ],
        'ticketing' => [
            'title' => 'Biglietteria — Savino Del Bene Volley',
            'description' => 'Acquista i biglietti per le partite casalinghe della Savino Del Bene Volley.',
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Passa oltre se non è un crawler social
        if (! self::isSocialCrawler($request)) {
            return $next($request);
        }

        $meta = $this->resolveMetaForPath($request);

        return response($this->buildMinimalHtml($meta, $request, self::localeOf($request)), 200)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Lingua della richiesta dedotta dal prefisso di rotta (`/en/...`).
     * Il middleware gira prima di SetLocale, quindi app()->getLocale() qui
     * varrebbe sempre 'it' e ogni anteprima inglese dichiarerebbe lang="it".
     */
    private static function localeOf(Request $request): string
    {
        return preg_match('#^en(/|$)#', ltrim($request->path(), '/')) === 1 ? 'en' : 'it';
    }

    /**
     * Pubblico e statico perché serve anche a CachePublicResponse: la risposta
     * ridotta servita ai crawler non deve MAI finire nella cache full-page
     * (né esserne servita), altrimenti tutti gli utenti anonimi vedrebbero
     * l'HTML minimale al posto della pagina vera.
     */
    public static function isSocialCrawler(Request $request): bool
    {
        $userAgent = $request->userAgent() ?? '';

        foreach (self::CRAWLER_PATTERNS as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Risolve i meta tag per il path corrente.
     * Per le news singole (/news/{slug}), carica titolo e immagine dal DB.
     */
    private function resolveMetaForPath(Request $request): array
    {
        $locale = self::localeOf($request);

        // Il prefisso di lingua non fa parte del path logico: senza toglierlo,
        // ogni URL inglese cadeva sul fallback generico della home.
        $path = ltrim($request->path(), '/');
        if ($locale === 'en') {
            $path = ltrim(preg_replace('#^en(/|$)#', '', $path) ?? '', '/');
        }

        // News singola: /news/{slug}
        if (preg_match('#^news/([\w\-]+)$#u', $path, $matches)) {
            return $this->resolveNewsMeta($matches[1], $locale);
        }

        // Prodotto shop: /shop/prodotto/{slug} — in inglese /shop/product/{slug}
        if (preg_match('#^shop/(?:prodotto|product)/([\w\-]+)$#u', $path, $matches)) {
            return $this->resolveProductMeta($matches[1], $locale);
        }

        // Pagine statiche
        $basePath = explode('/', $path)[0] ?? '';
        if (isset(self::PAGE_META[$path])) {
            return self::PAGE_META[$path];
        }

        // Pagina del CMS: copre gli slug che non stanno nella tabella qui sopra
        // (societa/storia, ticketing/abbonamenti, sponsor/…, comunicazione/…).
        if ($path !== '' && $meta = $this->resolveCmsPageMeta($path, $locale)) {
            return $meta;
        }

        if (isset(self::PAGE_META[$basePath])) {
            return self::PAGE_META[$basePath];
        }

        // Fallback generico
        return self::PAGE_META[''];
    }

    private function resolveNewsMeta(string $slug, string $locale = 'it'): array
    {
        // `published()` è obbligatorio: senza, un `curl -A "WhatsApp"` sullo slug
        // di una bozza ne restituiva titolo ed estratto. Stesso scope usato da
        // NewsController::show, così il crawler vede esattamente ciò che vede
        // il pubblico.
        $post = Post::published()->where('slug', $slug)->first();

        if (! $post) {
            return self::PAGE_META['news'];
        }

        // Il middleware gira prima di SetLocale: i campi tradotti vanno chiesti
        // esplicitamente nella lingua dell'URL, altrimenti l'anteprima inglese
        // riporta il testo italiano.
        $title = $this->translated($post, 'title', $locale);
        $excerpt = $this->translated($post, 'excerpt', $locale);
        $content = $this->translated($post, 'content', $locale);

        return [
            'title' => $title.' — Savino Del Bene Volley',
            'description' => $excerpt !== '' ? $excerpt : mb_substr(strip_tags($content), 0, 160),
            'image' => $post->getFirstMediaUrl('cover') ?: null,
        ];
    }

    private function resolveProductMeta(string $slug, string $locale): array
    {
        $product = Product::shoppable()->where('slug', $slug)->first();

        if (! $product) {
            return self::PAGE_META['shop'];
        }

        $name = $this->translated($product, 'name', $locale);
        $short = $this->translated($product, 'short_description', $locale);
        $description = $short !== ''
            ? $short
            : mb_substr(strip_tags($this->translated($product, 'description', $locale)), 0, 160);

        return [
            'title' => $name.' — Savino Del Bene Volley',
            'description' => $description,
            'image' => $product->getFirstMediaUrl('images', 'card')
                ?: ($product->getFirstMediaUrl('images') ?: null),
            'type' => 'product',
        ];
    }

    private function resolveCmsPageMeta(string $path, string $locale): ?array
    {
        // Gli URL di sezione (`/societa/storia`, `/ticketing/abbonamenti`)
        // portano lo slug nell'ultimo segmento, non nell'intero path.
        $candidates = array_unique([$path, basename($path)]);

        $page = Page::whereIn('slug', $candidates)
            ->where('status', PostStatus::Published)
            ->first();

        if (! $page) {
            return null;
        }

        $title = $this->translated($page, 'title', $locale);
        $metaDescription = $this->translated($page, 'meta_description', $locale);
        $excerpt = $this->translated($page, 'excerpt', $locale);
        $content = $this->translated($page, 'content', $locale);

        $description = $metaDescription !== '' ? $metaDescription : $excerpt;
        if ($description === '') {
            $description = mb_substr(strip_tags($content), 0, 160);
        }

        return [
            'title' => ($title !== '' ? $title : 'Savino Del Bene Volley').' — Savino Del Bene Volley',
            'description' => $description,
            'image' => $page->getFirstMediaUrl('cover') ?: null,
        ];
    }

    /**
     * Valore di un campo tradotto, normalizzato a stringa.
     */
    private function translated(object $model, string $field, string $locale): string
    {
        $value = method_exists($model, 'getTranslation')
            ? $model->getTranslation($field, $locale)
            : ($model->{$field} ?? '');

        return is_string($value) ? trim($value) : '';
    }

    private function buildMinimalHtml(array $meta, Request $request, string $locale = 'it'): string
    {
        $title = e($meta['title'] ?? 'Savino Del Bene Volley');
        $description = e($meta['description'] ?? '');
        $url = e($request->fullUrl());
        $siteName = 'Savino Del Bene Volley';
        $image = e($meta['image'] ?? url('/images/logo.png'));
        $lang = $locale === 'en' ? 'en' : 'it';
        $ogLocale = $locale === 'en' ? 'en_GB' : 'it_IT';
        $ogType = e($meta['type'] ?? 'website');

        return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    <meta property="og:type" content="{$ogType}">
    <meta property="og:title" content="{$title}">
    <meta property="og:description" content="{$description}">
    <meta property="og:url" content="{$url}">
    <meta property="og:image" content="{$image}">
    <meta property="og:locale" content="{$ogLocale}">
    <meta property="og:site_name" content="{$siteName}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{$title}">
    <meta name="twitter:description" content="{$description}">
    <meta name="twitter:image" content="{$image}">
</head>
<body>
    <h1>{$title}</h1>
    <p>{$description}</p>
</body>
</html>
HTML;
    }
}
