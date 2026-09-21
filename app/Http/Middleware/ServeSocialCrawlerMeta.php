<?php

namespace App\Http\Middleware;

use App\Enums\PostStatus;
use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Models\Player;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Intercetta i crawler social (Facebook, Twitter, WhatsApp, Telegram, LinkedIn)
 * e serve loro una pagina HTML minimale con i meta tag og: corretti.
 *
 * Questo sostituisce SSR per le anteprime social a costo zero.
 *
 * La pagina si riconosce dal NOME della rotta, non dal percorso: il nome è lo
 * stesso in tutte le lingue (`contatti`, `en.contatti`) mentre l'indirizzo no
 * (`/contatti`, `/en/contacts`). Con la tabella indicizzata per percorso ogni
 * pagina inglese con slug tradotto cadeva sul ripiego generico della home.
 *
 * Quello che non si sa descrivere passa oltre (`$next`): un'anteprima inventata
 * è peggio di quella statica del layout, e soprattutto il 301 delle sezioni
 * (`/ticketing` → `/ticketing/biglietteria`) e il 404 di un indirizzo che non
 * esiste devono restare tali anche per un crawler.
 */
class ServeSocialCrawlerMeta
{
    private const SITE_NAME = 'Savino Del Bene Volley';

    /** Coda del titolo di ogni pagina condivisa sui social. */
    private const TITLE_SUFFIX = ' — '.self::SITE_NAME;

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
     * Pagine senza una pagina del CMS da cui prendere i testi: nome della rotta
     * (senza prefisso di lingua) => chiave sotto `site.social` nelle traduzioni.
     *
     * Ci stanno solo le rotte che disegnano una pagina. Le rotte di rimando
     * (`risultati`, `societa`, `youth`, i redirect SEO del vecchio sito) e
     * quelle private del carrello restano fuori di proposito.
     */
    private const ROUTE_META = [
        'home' => 'home',
        'stagione' => 'stagione',
        'stagione.b1' => 'vivaio',
        'stagione.u17' => 'vivaio',
        'stagione.u15' => 'vivaio',
        'stagione.risultati' => 'risultati',
        'stagione.partita' => 'risultati',
        'stagione.classifica' => 'classifica',
        'stagione.cev' => 'cev',
        'stagione.coppa-italia' => 'coppa-italia',
        'stagione.playoff' => 'playoff',
        'stagione.foto-ufficiale' => 'foto-ufficiale',
        'gallery' => 'gallery',
        'staff' => 'staff',
        'sponsor' => 'sponsor',
        'shop' => 'shop',
        'shop.search' => 'shop',
        'shop.size-guide' => 'shop',
        'shop.contacts' => 'shop',
        'shop.auctions.index' => 'aste',
        'shop.auctions.show' => 'aste',
        'news.index' => 'news',
        'contatti' => 'contatti',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Passa oltre se non è un crawler social
        if (! self::isSocialCrawler($request)) {
            return $next($request);
        }

        $meta = $this->resolveMeta($request);

        if ($meta === null) {
            return $next($request);
        }

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
     * Meta tag della pagina richiesta, o null se non c'è niente da dire.
     *
     * @return array<string, mixed>|null
     */
    private function resolveMeta(Request $request): ?array
    {
        $route = $request->route();

        if (! $route instanceof RoutingRoute) {
            return null;
        }

        $locale = self::localeOf($request);

        // Pagine del CMS: si riconoscono dal controller, non dal percorso. Le
        // sezioni hanno nomi di rotta diversi fra loro (`societa.page`,
        // `ticketing.page`, `pages.show`) e `/summer-camp` passa il proprio
        // slug da `defaults()`, dove nel percorso non compare.
        if (str_starts_with((string) $route->getActionName(), PageController::class.'@show')) {
            $slug = $route->parameter('slug');

            return is_string($slug) ? $this->resolveCmsPageMeta($slug, $locale) : null;
        }

        $nome = $this->nomeDellaRotta($route, $locale);

        return match ($nome) {
            'news.show' => $this->resolveNewsMeta($this->slugDiRotta($route, 'slug'), $locale),
            'shop.product' => $this->resolveProductMeta($this->slugDiRotta($route, 'product'), $locale),
            'shop.category' => $this->resolveCategoryMeta($this->slugDiRotta($route, 'category'), $locale),
            'stagione.atleta' => $this->resolvePlayerMeta($this->slugDiRotta($route, 'slug'), $locale, inRosa: true),
            'gallery.atleta' => $this->resolvePlayerMeta($this->slugDiRotta($route, 'slug'), $locale, inRosa: false),
            default => isset(self::ROUTE_META[$nome])
                ? $this->metaTradotti(self::ROUTE_META[$nome], $locale)
                : null,
        };
    }

    /**
     * Nome della rotta senza il prefisso della lingua: `en.contatti` e
     * `contatti` sono la stessa pagina.
     */
    private function nomeDellaRotta(RoutingRoute $route, string $locale): string
    {
        $nome = (string) $route->getName();

        return str_starts_with($nome, $locale.'.') ? substr($nome, strlen($locale) + 1) : $nome;
    }

    /**
     * Slug di un parametro di rotta. Il route model binding gira prima di
     * questo middleware, quindi il parametro può essere già un modello.
     */
    private function slugDiRotta(RoutingRoute $route, string $parametro): string
    {
        $valore = $route->parameter($parametro);

        if (is_object($valore)) {
            return (string) ($valore->slug ?? '');
        }

        return is_scalar($valore) ? (string) $valore : '';
    }

    /**
     * Testi fissi di una pagina, presi dalle traduzioni nella lingua dell'URL.
     *
     * @param  array<string, string>  $sostituzioni
     * @return array<string, mixed>
     */
    private function metaTradotti(string $chiave, string $locale, array $sostituzioni = []): array
    {
        return [
            'title' => (string) __('site.social.'.$chiave.'.title', $sostituzioni, $locale),
            'description' => (string) __('site.social.'.$chiave.'.description', $sostituzioni, $locale),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveNewsMeta(string $slug, string $locale = 'it'): ?array
    {
        // `published()` è obbligatorio: senza, un `curl -A "WhatsApp"` sullo slug
        // di una bozza ne restituiva titolo ed estratto. Stesso scope usato da
        // NewsController::show, così il crawler vede esattamente ciò che vede
        // il pubblico.
        $post = Post::published()->where('slug', $slug)->first();

        if (! $post) {
            return null;
        }

        // Il middleware gira prima di SetLocale: i campi tradotti vanno chiesti
        // esplicitamente nella lingua dell'URL, altrimenti l'anteprima inglese
        // riporta il testo italiano.
        $title = $this->translated($post, 'title', $locale);
        $excerpt = $this->translated($post, 'excerpt', $locale);
        $content = $this->translated($post, 'content', $locale);

        return [
            'title' => $title,
            'description' => $excerpt !== '' ? $excerpt : mb_substr(strip_tags($content), 0, 160),
            'image' => $post->getFirstMediaUrl('cover') ?: null,
            'type' => 'article',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveProductMeta(string $slug, string $locale): ?array
    {
        // Il binding risolve qualunque prodotto: lo scope pubblico va riapplicato
        // qui, come per le bozze delle news.
        $product = Product::shoppable()->where('slug', $slug)->first();

        if (! $product) {
            return null;
        }

        $name = $this->translated($product, 'name', $locale);
        $short = $this->translated($product, 'short_description', $locale);
        $description = $short !== ''
            ? $short
            : mb_substr(strip_tags($this->translated($product, 'description', $locale)), 0, 160);

        return [
            'title' => $name,
            'description' => $description,
            'image' => $product->getFirstMediaUrl('images', 'card')
                ?: ($product->getFirstMediaUrl('images') ?: null),
            'type' => 'product',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCategoryMeta(string $slug, string $locale): ?array
    {
        $categoria = ProductCategory::where('slug', $slug)->first();

        if (! $categoria) {
            return null;
        }

        $name = $this->translated($categoria, 'name', $locale);
        $description = mb_substr(strip_tags($this->translated($categoria, 'description', $locale)), 0, 160);

        return [
            'title' => $name,
            'description' => $description !== ''
                ? $description
                : (string) __('site.social.shop.description', [], $locale),
        ];
    }

    /**
     * Scheda di un'atleta. Lo slug è `{id}-{nome-cognome}`, come lo costruisce
     * PublicController::presentRoster.
     *
     * @return array<string, mixed>|null
     */
    private function resolvePlayerMeta(string $slug, string $locale, bool $inRosa): ?array
    {
        $id = (int) explode('-', $slug)[0];

        if ($id <= 0) {
            return null;
        }

        $player = Player::with('media')->find($id);

        if (! $player) {
            return null;
        }

        if ($inRosa) {
            // La pagina della stagione risponde 404 a uno slug che non è quello
            // di un'atleta in rosa: l'anteprima non può dire il contrario.
            if ($slug !== $player->id.'-'.Str::slug($player->full_name)) {
                return null;
            }

            $riga = $this->rigaDiRosa($player);

            if (! $riga) {
                return null;
            }

            return [
                ...$this->metaTradotti('atleta', $locale, ['nome' => $player->full_name]),
                'image' => $riga->official_photo_url,
                'type' => 'profile',
            ];
        }

        return [
            ...$this->metaTradotti('gallery-atleta', $locale, ['nome' => $player->full_name]),
            'image' => $player->getFirstMediaUrl('players') ?: null,
        ];
    }

    /**
     * Riga di rosa dell'atleta nella stagione corrente, con la foto ufficiale
     * già risolta (accessor `official_photo_url` di Roster).
     *
     * Squadra e stagione sono scelte come in PublicController::stagioneForTeam:
     * `/stagione/atleta/{slug}` è solo la prima squadra, e un'atleta del vivaio
     * lì risponde 404. Se qui bastasse una squadra interna qualunque, il
     * crawler riceverebbe l'anteprima di una pagina che non esiste.
     */
    private function rigaDiRosa(Player $player): ?Roster
    {
        $stagione = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

        $squadra = Team::where('is_internal', true)
            ->where(fn ($query) => $query
                ->where('slug', 'savino-del-bene-volley')
                ->orWhere('category', 'A1'))
            ->first();

        if (! $stagione || ! $squadra) {
            return null;
        }

        return Roster::with(['media', 'player.media'])
            ->where('player_id', $player->id)
            ->where('season_id', $stagione->id)
            ->where('team_id', $squadra->id)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCmsPageMeta(string $slug, string $locale): ?array
    {
        $page = Page::where('slug', $slug)
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
            'title' => $title !== '' ? $title : self::SITE_NAME,
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

    /**
     * Il nome della società chiude ogni titolo, ma una volta sola: i titoli
     * delle pagine del CMS e quello della home lo contengono già.
     */
    private function conIlNomeDelSito(string $titolo): string
    {
        if ($titolo === '' || str_contains($titolo, self::SITE_NAME)) {
            return $titolo !== '' ? $titolo : self::SITE_NAME;
        }

        return $titolo.self::TITLE_SUFFIX;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function buildMinimalHtml(array $meta, Request $request, string $locale = 'it'): string
    {
        $title = e($this->conIlNomeDelSito((string) ($meta['title'] ?? '')));
        $description = e($meta['description'] ?? '');
        $url = e($request->fullUrl());
        $siteName = self::SITE_NAME;
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
