<?php

namespace App\Http\Middleware;

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
        if (! $this->isSocialCrawler($request)) {
            return $next($request);
        }

        $meta = $this->resolveMetaForPath($request);

        return response($this->buildMinimalHtml($meta, $request), 200)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function isSocialCrawler(Request $request): bool
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
        $path = ltrim($request->path(), '/');

        // News singola: /news/{slug}
        if (preg_match('#^news/([a-z0-9\-]+)$#', $path, $matches)) {
            return $this->resolveNewsMeta($matches[1]);
        }

        // Prodotto shop: /shop/{slug} (se implementato in futuro)

        // Pagine statiche
        $basePath = explode('/', $path)[0] ?? '';
        if (isset(self::PAGE_META[$path])) {
            return self::PAGE_META[$path];
        }
        if (isset(self::PAGE_META[$basePath])) {
            return self::PAGE_META[$basePath];
        }

        // Fallback generico
        return self::PAGE_META[''];
    }

    private function resolveNewsMeta(string $slug): array
    {
        $post = \App\Models\Post::where('slug', $slug)->first();

        if (! $post) {
            return self::PAGE_META['news'];
        }

        return [
            'title' => $post->title.' — Savino Del Bene Volley',
            'description' => $post->excerpt ?? mb_substr(strip_tags($post->body ?? ''), 0, 160),
            'image' => $post->getFirstMediaUrl('post-images') ?: null,
        ];
    }

    private function buildMinimalHtml(array $meta, Request $request): string
    {
        $title = e($meta['title'] ?? 'Savino Del Bene Volley');
        $description = e($meta['description'] ?? '');
        $url = e($request->fullUrl());
        $siteName = 'Savino Del Bene Volley';
        $image = e($meta['image'] ?? url('/images/logo.png'));

        return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{$title}">
    <meta property="og:description" content="{$description}">
    <meta property="og:url" content="{$url}">
    <meta property="og:image" content="{$image}">
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
