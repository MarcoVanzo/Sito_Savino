<?php

namespace App\Http\Middleware;

use App\Enums\GameStatus;
use App\Enums\PostStatus;
use App\Http\Controllers\PageController;
use App\Models\Auction;
use App\Models\Game;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Player;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use App\Services\GalleryArchive;
use App\Services\Seo\DatiStrutturati;
use App\Support\TestoDallHtml;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * I meta di ogni pagina pubblica, calcolati sul server: titolo, descrizione,
 * immagine, dati strutturati (JSON-LD) e briciole di pane.
 *
 * Tre destinatari, una sola fonte:
 * - i crawler dei social ricevono un HTML minimale con i meta og: (sostituisce
 *   l'SSR a costo zero);
 * - il primo caricamento di chiunque altro li trova nell'HTML del layout
 *   (app.blade.php), JSON-LD compreso: senza SSR Google e chi non esegue il
 *   JavaScript non vedrebbero altro;
 * - le visite Inertia ricevono titolo e descrizione nella prop condivisa
 *   `seo` (HandleInertiaRequests), che il client usa così com'è: il titolo
 *   della scheda non cambia fra HTML del server e pagina disegnata.
 *
 * La pagina si riconosce dal NOME della rotta, non dal percorso: il nome è lo
 * stesso in tutte le lingue (`contatti`, `en.contatti`) mentre l'indirizzo no.
 *
 * Quello che non si sa descrivere passa oltre (`$next`): un'anteprima inventata
 * è peggio di quella statica del layout, e soprattutto il 301 delle sezioni
 * (`/ticketing` → `/ticketing/biglietteria`) e il 404 di un indirizzo che non
 * esiste devono restare tali anche per un crawler.
 */
class ServeSocialCrawlerMeta
{
    private const SITE_NAME = DatiStrutturati::NOME;

    /** Coda del titolo di ogni pagina, col nome della società. */
    private const TITLE_SUFFIX = ' | '.self::SITE_NAME;

    /**
     * Oltre questa lunghezza il titolo di una notizia perde la coda col nome
     * della società invece di farsi troncare da Google a metà frase.
     */
    private const TITOLO_MAX = 60;

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
     * Ogni pagina ha la sua chiave: due rotte con gli stessi testi sono due
     * pagine con titolo e descrizione duplicati (audit SEO del 14/10/2026).
     * Le rotte di rimando (`risultati`, `societa`, `youth`, i redirect del
     * vecchio sito) restano fuori di proposito: non disegnano una pagina.
     */
    private const ROUTE_META = [
        'home' => 'home',
        'stagione' => 'stagione',
        'stagione.b1' => 'vivaio-b1',
        'stagione.u17' => 'vivaio-u17',
        'stagione.u15' => 'vivaio-u15',
        'stagione.risultati' => 'risultati',
        'stagione.classifica' => 'classifica',
        'stagione.cev' => 'cev',
        'stagione.coppa-italia' => 'coppa-italia',
        'stagione.playoff' => 'playoff',
        'stagione.foto-ufficiale' => 'foto-ufficiale',
        'gallery' => 'gallery',
        'diretta' => 'diretta',
        'staff' => 'staff',
        'sponsor' => 'sponsor',
        'shop' => 'shop',
        'shop.search' => 'shop-cerca',
        'shop.size-guide' => 'shop-guida-taglie',
        'shop.contacts' => 'shop-contatti',
        'shop.auctions.index' => 'aste',
        'shop.cart' => 'carrello',
        'shop.checkout' => 'checkout',
        'shop.checkout.success' => 'checkout-conferma',
        'shop.checkout.cancel' => 'checkout-annullato',
        'shop.auction-checkout.show' => 'checkout',
        'shop.auction-checkout.success' => 'checkout-conferma',
        'shop.auction-checkout.cancel' => 'checkout-annullato',
        'shop.account' => 'account',
        'shop.orders' => 'ordini',
        'shop.order.show' => 'ordine',
        'shop.register' => 'registrati',
        'recesso' => 'recesso',
        'recesso.ricevuta' => 'recesso',
        'newsletter.conferma.show' => 'newsletter',
        'newsletter.unsubscribe.show' => 'newsletter',
        'in-costruzione' => 'in-costruzione',
        'news.index' => 'news',
        'contatti' => 'contatti',
    ];

    /**
     * Le pagine del calendario e la competizione delle loro gare.
     */
    private const CALENDARI = [
        'stagione.risultati' => 'Campionato',
        'stagione.cev' => 'Champions League',
        'stagione.coppa-italia' => 'Coppa Italia',
        'stagione.playoff' => 'Playoff',
    ];

    /** Attributo della richiesta da cui app.blade.php legge i meta della pagina. */
    public const ATTRIBUTO_META = 'meta_della_pagina';

    public function __construct(private readonly DatiStrutturati $dati) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::isSocialCrawler($request)) {
            if ($request->isMethod('GET')) {
                // Le visite Inertia non disegnano il layout: a loro servono
                // solo titolo e descrizione, per la prop `seo`. I dati
                // strutturati si calcolano per il primo caricamento.
                $conIDati = ! $request->header('X-Inertia');
                $meta = $this->resolveMeta($request, $conIDati);

                if ($meta !== null) {
                    $request->attributes->set(self::ATTRIBUTO_META, $this->perIlLayout($meta));
                }
            }

            return $next($request);
        }

        $meta = $this->resolveMeta($request, false);

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
     * Meta della pagina richiesta, o null se non c'è niente da dire.
     *
     * Con `$conIDati` aggiunge i blocchi JSON-LD (`jsonld`): costano qualche
     * query in più e servono solo all'HTML del primo caricamento.
     *
     * @return array<string, mixed>|null
     */
    private function resolveMeta(Request $request, bool $conIDati): ?array
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
            $meta = is_string($slug) ? $this->resolveCmsPageMeta($slug, $locale) : null;

            return $meta !== null && $conIDati
                ? $this->conLeBriciole($meta, $request, $locale)
                : $meta;
        }

        $nome = $this->nomeDellaRotta($route, $locale);

        $meta = match ($nome) {
            'news.show' => $this->resolveNewsMeta($this->slugDiRotta($route, 'slug'), $locale, $conIDati),
            'shop.product' => $this->resolveProductMeta($this->slugDiRotta($route, 'product'), $locale, $conIDati),
            'shop.category' => $this->resolveCategoryMeta($this->slugDiRotta($route, 'category'), $locale, $conIDati),
            'shop.auctions.show' => $this->resolveAuctionMeta($route->parameter('auction'), $locale),
            'stagione.atleta' => $this->resolvePlayerMeta($this->slugDiRotta($route, 'slug'), $locale, true, $conIDati),
            'stagione.partita' => $this->resolveGameMeta($route->parameter('game'), $locale, $conIDati),
            'gallery.atleta' => $this->resolvePlayerMeta($this->slugDiRotta($route, 'slug'), $locale, false, $conIDati),
            'gallery.album' => $this->resolveAlbumMeta($this->slugDiRotta($route, 'slug'), $locale),
            default => isset(self::ROUTE_META[$nome])
                ? $this->metaTradotti(self::ROUTE_META[$nome], $locale)
                : null,
        };

        if ($meta === null || ! $conIDati) {
            return $meta;
        }

        $meta['jsonld'] = [
            ...($meta['jsonld'] ?? []),
            ...$this->datiDellaPaginaFissa($nome, $locale),
        ];

        return $nome === 'home' ? $meta : $this->conLeBriciole($meta, $request, $locale);
    }

    /**
     * JSON-LD delle pagine fisse: la società e il sito solo in home, con la
     * gara da annunciare; le gare della società sulle pagine del calendario.
     *
     * @return list<array<string, mixed>>
     */
    private function datiDellaPaginaFissa(string $nome, string $locale): array
    {
        if ($nome === 'home') {
            $gara = $this->dati->garaDellaHome();

            return array_values(array_filter([
                $this->dati->squadra(),
                $this->dati->sito($locale),
                $gara !== null ? $this->dati->gara($gara, $locale) : null,
            ]));
        }

        if (isset(self::CALENDARI[$nome])) {
            $stagione = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

            if (! $stagione) {
                return [];
            }

            $gare = Game::with(['homeTeam.media', 'awayTeam.media'])
                ->withExists('playerStats')
                ->where('season_id', $stagione->id)
                ->where('competition_type', self::CALENDARI[$nome])
                ->where(fn ($query) => $query
                    ->whereHas('homeTeam', fn ($team) => $team->where('is_internal', true))
                    ->orWhereHas('awayTeam', fn ($team) => $team->where('is_internal', true)))
                ->orderBy('match_date')
                ->get();

            return $this->dati->gare($gare, $locale);
        }

        return [];
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
    private function resolveNewsMeta(string $slug, string $locale, bool $conIDati): ?array
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
        $metaDescription = $this->translated($post, 'meta_description', $locale);
        $excerpt = $this->translated($post, 'excerpt', $locale);
        $content = $this->translated($post, 'content', $locale);

        $description = $metaDescription !== '' ? $metaDescription : $excerpt;

        return [
            'title' => $title,
            'description' => $description !== '' ? $description : mb_substr(TestoDallHtml::testo($content), 0, 160),
            'image' => $post->getFirstMediaUrl('cover') ?: null,
            'type' => 'article',
            'jsonld' => $conIDati ? [$this->dati->notizia($post, $locale)] : [],
            'briciole' => [['name' => $this->etichetta('news', $locale), 'url' => route($this->prefisso($locale).'news.index')]],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveProductMeta(string $slug, string $locale, bool $conIDati): ?array
    {
        // Il binding risolve qualunque prodotto: lo scope pubblico va riapplicato
        // qui, come per le bozze delle news.
        $product = Product::shoppable()->with(['media', 'category', 'variants'])->where('slug', $slug)->first();

        if (! $product) {
            return null;
        }

        $name = $this->translated($product, 'name', $locale);
        $short = $this->translated($product, 'short_description', $locale);
        $description = $short !== ''
            ? $short
            : mb_substr(TestoDallHtml::testo($this->translated($product, 'description', $locale)), 0, 160);

        $briciole = [['name' => $this->etichetta('shop', $locale), 'url' => route($this->prefisso($locale).'shop')]];

        if ($product->category) {
            $briciole[] = [
                'name' => $this->translated($product->category, 'name', $locale),
                'url' => route($this->prefisso($locale).'shop.category', ['category' => $product->category->slug]),
            ];
        }

        return [
            'title' => $name,
            'description' => $description !== ''
                ? $description
                : (string) __('site.social.prodotto.description', ['nome' => $name], $locale),
            'image' => $product->getFirstMediaUrl('images', 'card')
                ?: ($product->getFirstMediaUrl('images') ?: null),
            'type' => 'product',
            'jsonld' => $conIDati ? [$this->dati->prodotto($product, $locale)] : [],
            'briciole' => $briciole,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCategoryMeta(string $slug, string $locale, bool $conIDati): ?array
    {
        $categoria = ProductCategory::where('slug', $slug)->first();

        if (! $categoria) {
            return null;
        }

        $name = $this->translated($categoria, 'name', $locale);
        $description = mb_substr(TestoDallHtml::testo($this->translated($categoria, 'description', $locale)), 0, 160);

        $jsonld = [];

        if ($conIDati) {
            // Lo stesso insieme che la pagina mostra: la categoria e i suoi
            // scaffali (ShopController::categoryShow), in ordine di vetrina.
            $categorie = [$categoria->id, ...$categoria->children()->pluck('id')->all()];
            $prodotti = Product::shoppable()->nelleCategorie($categorie)->inOrdineDiVetrina()->take(50)->get();
            $jsonld[] = $this->dati->categoria($categoria, $prodotti, $locale);
        }

        return [
            'title' => $name,
            // La descrizione dello shop su ogni categoria era una descrizione
            // duplicata: quella generata nomina la categoria.
            'description' => $description !== ''
                ? $description
                : (string) __('site.social.shop-categoria.description', ['nome' => $name], $locale),
            'jsonld' => $jsonld,
            'briciole' => [['name' => $this->etichetta('shop', $locale), 'url' => route($this->prefisso($locale).'shop')]],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAuctionMeta(mixed $asta, string $locale): ?array
    {
        if (! $asta instanceof Auction) {
            return null;
        }

        $titolo = $this->translated($asta, 'title', $locale);
        $descrizione = mb_substr(trim(strip_tags($this->translated($asta, 'description', $locale))), 0, 160);

        return [
            'title' => $titolo !== '' ? $titolo : (string) __('site.social.aste.title', [], $locale),
            'description' => $descrizione !== ''
                ? $descrizione
                : (string) __('site.social.asta.description', ['nome' => $titolo], $locale),
            'briciole' => [['name' => $this->etichetta('aste', $locale), 'url' => route($this->prefisso($locale).'shop.auctions.index')]],
        ];
    }

    /**
     * La scheda di una partita giocata: squadre, data e competizione nel
     * titolo, una per gara invece del titolo del calendario su tutte.
     *
     * @return array<string, mixed>|null
     */
    private function resolveGameMeta(mixed $gara, string $locale, bool $conIDati): ?array
    {
        if (! $gara instanceof Game) {
            $gara = Game::find((int) $gara);
        }

        if (! $gara instanceof Game || ! $gara->homeTeam || ! $gara->awayTeam) {
            return null;
        }

        // La scheda esiste solo per le gare giocate con il tabellino
        // (RisultatiController::partita): per le altre il controller risponde
        // 404, e il crawler non deve ricevere un'anteprima al suo posto.
        if ($gara->status !== GameStatus::Completed
            || $gara->home_score === null
            || $gara->away_score === null
            || ! $gara->playerStats()->exists()) {
            return null;
        }

        $sostituzioni = [
            'casa' => $gara->homeTeam->nomePubblico(),
            'ospite' => $gara->awayTeam->nomePubblico(),
            'data' => $gara->match_date->format('d/m/Y'),
            'competizione' => $gara->competition_type?->getLabel() ?? '',
            'risultato' => $gara->home_score.'-'.$gara->away_score,
        ];

        return [
            ...$this->metaTradotti('partita', $locale, $sostituzioni),
            'image' => $gara->homeTeam->is_internal ? $gara->awayTeam->logoUrl() : $gara->homeTeam->logoUrl(),
            'jsonld' => $conIDati ? array_filter([$this->dati->gara($gara, $locale)]) : [],
            'briciole' => [
                ['name' => $this->etichetta('stagione', $locale), 'url' => route($this->prefisso($locale).'stagione')],
                ['name' => $this->etichetta('risultati', $locale), 'url' => route($this->prefisso($locale).'stagione.risultati')],
            ],
        ];
    }

    /**
     * Album della gallery (`{id}-{titolo}`): titolo e prima foto, letti
     * dall'archivio come fa GalleryController::galleryAlbum, così l'anteprima
     * esiste solo per gli album che la pagina mostra.
     *
     * @return array<string, mixed>|null
     */
    private function resolveAlbumMeta(string $slug, string $locale): ?array
    {
        $id = (int) explode('-', $slug)[0];

        if ($id <= 0) {
            return null;
        }

        foreach (app(GalleryArchive::class)->media(null, $locale) as $foto) {
            if ((int) ($foto['event_id'] ?? 0) === $id) {
                return [
                    ...$this->metaTradotti('gallery-album', $locale, ['nome' => $foto['event_name'] ?? '']),
                    'image' => $foto['url'] ?? null,
                    'briciole' => [['name' => $this->etichetta('gallery', $locale), 'url' => route($this->prefisso($locale).'gallery')]],
                ];
            }
        }

        return null;
    }

    /**
     * Scheda di un'atleta. Lo slug è `{id}-{nome-cognome}`, come lo costruisce
     * PublicController::presentRoster.
     *
     * @return array<string, mixed>|null
     */
    private function resolvePlayerMeta(string $slug, string $locale, bool $inRosa, bool $conIDati): ?array
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
            // Con uno slug che non è quello giusto, o un'atleta fuori rosa, la
            // pagina dell'atleta risponde con un redirect (o 404 se l'atleta
            // non c'è): l'anteprima non può dire il contrario.
            if ($slug !== $player->id.'-'.Str::slug($player->full_name)) {
                return null;
            }

            $riga = self::rigaDiRosa($player);

            if (! $riga) {
                return null;
            }

            $ruolo = $riga->role !== null
                ? (string) __('site.ruoli.'.$riga->role->value, [], $locale)
                : '';
            $sostituzioni = [
                'nome' => $player->full_name,
                'ruolo' => $ruolo,
                'numero' => (string) ($riga->jersey_number ?? ''),
            ];
            $indirizzo = route($this->prefisso($locale).'stagione.atleta', ['slug' => $slug]);

            return [
                'title' => $ruolo !== ''
                    ? (string) __('site.social.atleta.title', $sostituzioni, $locale)
                    : $player->full_name,
                'description' => (string) __($ruolo !== '' ? 'site.social.atleta.description' : 'site.social.atleta.description_senza_ruolo', $sostituzioni, $locale),
                'image' => $riga->official_photo_url,
                'type' => 'profile',
                'jsonld' => $conIDati ? [$this->dati->atleta($riga, $ruolo, $indirizzo)] : [],
                'briciole' => [['name' => $this->etichetta('stagione', $locale), 'url' => route($this->prefisso($locale).'stagione')]],
            ];
        }

        return [
            ...$this->metaTradotti('gallery-atleta', $locale, ['nome' => $player->full_name]),
            'image' => $player->getFirstMediaUrl('players') ?: null,
            'briciole' => [['name' => $this->etichetta('gallery', $locale), 'url' => route($this->prefisso($locale).'gallery')]],
        ];
    }

    /**
     * Riga di rosa dell'atleta nella stagione corrente, con la foto ufficiale
     * già risolta (accessor `official_photo_url` di Roster).
     *
     * Squadra e stagione sono scelte come in PublicController::stagioneForTeam:
     * `/stagione/atleta/{slug}` è solo la prima squadra, e un'atleta del vivaio
     * lì risponde 404. Pubblico perché la pagina dell'atleta cerca la stessa
     * riga: le due devono rispondere sì e no insieme.
     */
    public static function rigaDiRosa(Player $player): ?Roster
    {
        [$stagione, $squadra] = self::stagioneESquadraDellaRosa();

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
     * Se la rosa della prima squadra nella stagione corrente ha già delle
     * atlete. A inizio stagione la Season nuova nasce prima delle rose: in
     * quei giorni un'atleta "fuori rosa" non lo è davvero.
     */
    public static function rosaDellaStagioneCompilata(): bool
    {
        [$stagione, $squadra] = self::stagioneESquadraDellaRosa();

        return $stagione && $squadra && Roster::where('season_id', $stagione->id)
            ->where('team_id', $squadra->id)
            ->exists();
    }

    /**
     * @return array{0: ?Season, 1: ?Team}
     */
    private static function stagioneESquadraDellaRosa(): array
    {
        $stagione = Season::current()->latest('id')->first() ?? Season::latest('id')->first();

        $squadra = Team::where('is_internal', true)
            ->where(fn ($query) => $query
                ->where('slug', 'savino-del-bene-volley')
                ->orWhere('category', 'A1'))
            ->first();

        return [$stagione, $squadra];
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
            $description = mb_substr(trim(strip_tags($content)), 0, 160);
        }
        if ($description === '' && $title !== '') {
            $description = (string) __('site.social.pagina.description', ['nome' => $title], $locale);
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
     * Il titolo breve di una pagina fissa, per le briciole.
     */
    private function etichetta(string $chiave, string $locale): string
    {
        return (string) __('site.social.'.$chiave.'.title', [], $locale);
    }

    private function prefisso(string $locale): string
    {
        return $locale === config('app.fallback_locale') ? '' : $locale.'.';
    }

    /**
     * Aggiunge le briciole di pane (BreadcrumbList) ai dati strutturati.
     *
     * Coerenti con il menu: se la pagina è una voce del menu principale, le
     * briciole sono la sua sezione e lei; altrimenti la catena che ha dato
     * chi ha risolto la pagina (`briciole`: la notizia sta sotto News, il
     * prodotto sotto Shop e la sua categoria). Ultima voce la pagina stessa.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function conLeBriciole(array $meta, Request $request, string $locale): array
    {
        $home = ['name' => self::SITE_NAME, 'url' => route($this->prefisso($locale).'home')];
        $pagina = ['name' => (string) ($meta['title'] ?? ''), 'url' => null];

        $catena = $meta['briciole'] ?? $this->sezioneDelMenu($request);

        $blocco = $this->dati->briciole([$home, ...$catena, $pagina]);

        if ($blocco !== null) {
            $meta['jsonld'] = [...($meta['jsonld'] ?? []), $blocco];
        }

        return $meta;
    }

    /**
     * La sezione del menu principale che contiene la pagina corrente, se c'è.
     * Una sezione senza un indirizzo diverso da quello della pagina non fa da
     * anello: sarebbe la stessa pagina due volte.
     *
     * @return list<array{name: string, url: string}>
     */
    private function sezioneDelMenu(Request $request): array
    {
        $corrente = '/'.trim($request->path(), '/');

        foreach (MenuItem::getTree('main') as $sezione) {
            foreach ($sezione['children'] ?? [] as $voce) {
                if ($this->percorso((string) ($voce['href'] ?? '')) !== $corrente) {
                    continue;
                }

                $indirizzo = (string) ($sezione['href'] ?? '');

                return $indirizzo !== '' && $this->percorso($indirizzo) !== $corrente
                    ? [['name' => (string) $sezione['label'], 'url' => url($this->percorso($indirizzo))]]
                    : [];
            }
        }

        return [];
    }

    private function percorso(string $indirizzo): string
    {
        return '/'.trim((string) parse_url($indirizzo, PHP_URL_PATH), '/');
    }

    /**
     * Il titolo completo: la coda col nome della società, una volta sola (i
     * titoli del CMS e quello della home lo contengono già).
     *
     * Una notizia con un titolo lungo perde la coda: meglio il titolo intero
     * senza il nome della società che il titolo tagliato da Google.
     *
     * @param  array<string, mixed>  $meta
     */
    private function titoloCompleto(array $meta): string
    {
        $titolo = trim((string) ($meta['title'] ?? ''));

        if ($titolo === '') {
            return self::SITE_NAME;
        }

        if (str_contains($titolo, self::SITE_NAME)) {
            return $titolo;
        }

        $completo = $titolo.self::TITLE_SUFFIX;

        if (($meta['type'] ?? null) === 'article' && mb_strlen($completo) > self::TITOLO_MAX) {
            return $titolo;
        }

        return $completo;
    }

    /**
     * I meta pronti per il layout e per la prop `seo`: titolo completo,
     * descrizione in testo semplice, immagine, tipo e JSON-LD.
     *
     * @param  array<string, mixed>  $meta
     * @return array{title: string, description: string, image: ?string, type: string, jsonld: list<array<string, mixed>>}
     */
    private function perIlLayout(array $meta): array
    {
        $descrizione = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($meta['description'] ?? ''))));

        return [
            'title' => $this->titoloCompleto($meta),
            'description' => Str::limit(html_entity_decode($descrizione, ENT_QUOTES | ENT_HTML5), 200),
            'image' => ($meta['image'] ?? null) ?: null,
            'type' => (string) ($meta['type'] ?? 'website'),
            'jsonld' => array_values($meta['jsonld'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function buildMinimalHtml(array $meta, Request $request, string $locale = 'it'): string
    {
        $title = e($this->titoloCompleto($meta));
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
