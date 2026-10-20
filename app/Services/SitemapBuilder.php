<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Models\Player;
use App\Models\Post;
use App\Models\Product;
use App\Models\Reindirizzamento;
use App\Models\Roster;
use App\Models\Season;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Services\Seo\DatiStrutturati;
use App\Support\IndirizziPerLingua;
use App\Support\RedirectGestiti;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Costruisce la sitemap del sito pubblico.
 *
 * Gli URL nascono da `url()`, quindi seguono APP_URL dell'ambiente che serve la
 * richiesta. È il motivo per cui la sitemap NON va scritta come file statico in
 * `public/`: quella committata nel repository conteneva `http://localhost:8000`
 * su tutti i 378 URL, perché era stata generata sulla macchina di sviluppo e poi
 * versionata. Un file in `public/` viene inoltre servito dal web server prima
 * che Laravel veda la richiesta, quindi vincerebbe comunque su questa rotta.
 */
class SitemapBuilder
{
    private const CACHE_KEY = 'sitemap.xml';

    /**
     * Le sitemap del sito, una per sezione, servite da `/sitemap-{parte}.xml`
     * e tenute insieme dall'indice: in un file solo stavano 2.700 indirizzi e
     * 13.000 immagini, e Search Console non diceva quale sezione avesse errori.
     */
    public const PARTI = ['pagine', 'notizie', 'prodotti', 'atlete', 'gallery'];

    private const CACHE_TTL = 60 * 60 * 6;

    private const CACHE_KEY_NOTIZIE = 'sitemap-news.xml';

    /** La sitemap delle notizie cambia con ogni articolo: mezz'ora. */
    private const CACHE_TTL_NOTIZIE = 60 * 30;

    /**
     * XML di una parte della sitemap, ricalcolato al più ogni sei ore.
     */
    public function render(string $parte): string
    {
        return Cache::remember($this->chiave($parte), self::CACHE_TTL, fn () => $this->genera($parte));
    }

    /**
     * Ricalcola subito tutte le parti e ne aggiorna la cache.
     *
     * @return array<string, string> l'XML di ogni parte
     */
    public function refresh(): array
    {
        $parti = [];

        foreach (self::PARTI as $parte) {
            $parti[$parte] = $this->genera($parte);
            Cache::put($this->chiave($parte), $parti[$parte], self::CACHE_TTL);
        }

        return $parti;
    }

    /**
     * La chiave di cache di una parte. Quella dei prodotti cambia con lo
     * stato del negozio: chiuso lo shop, le schede sparivano dalla sitemap
     * solo alla scadenza della cache (fino a sei ore).
     */
    private function chiave(string $parte): string
    {
        $chiave = self::CACHE_KEY.':'.$parte;

        if ($parte === 'prodotti') {
            $chiave .= self::shopAperto() ? ':aperto' : ':chiuso';
        }

        return $chiave;
    }

    private static function shopAperto(): bool
    {
        return filter_var(SiteSetting::get('shop.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * L'XML di una parte, annotando la modifica più recente dei suoi
     * indirizzi: è il `lastmod` della parte nell'indice. Con l'ora di
     * generazione cambiava ogni sei ore anche senza novità, e Google impara a
     * ignorarlo. Senza date negli indirizzi non si scrive.
     */
    private function genera(string $parte): string
    {
        $sitemap = $this->build($parte);
        $ultima = collect($sitemap->getTags())
            ->map(fn ($tag) => $tag instanceof Url ? $tag->lastModificationDate : null)
            ->filter()
            ->max();

        Cache::put($this->chiave($parte).':modificata', $ultima?->getTimestamp(), self::CACHE_TTL);

        return $sitemap->render();
    }

    /**
     * Una parte della sitemap, o tutte insieme (null): l'unione serve ai
     * controlli che passano ogni indirizzo.
     */
    public function build(?string $parte = null): Sitemap
    {
        $sitemap = Sitemap::create();

        // Gli indirizzi che il pannello reindirizza non vanno in sitemap: una
        // sitemap di rimandi Google la considera un errore.
        $reindirizzati = array_flip(Reindirizzamento::query()->pluck('from_path')->all());

        $addLocalizedUrls = function (string $path, array $config = [], $lastMod = null, array $immagini = []) use ($sitemap, $reindirizzati): void {
            if (isset($reindirizzati[RedirectGestiti::normalizza($path) ?? ''])) {
                return;
            }

            // Il percorso è quello della lingua predefinita; gli altri si
            // chiedono al router per nome di rotta, perché gli slug sono
            // tradotti: con `/en` davanti al percorso la pagina Contatti
            // finiva in sitemap come `/en/contatti`, che è un 404 (la rotta
            // inglese è `/en/contacts`). Una lingua in cui la pagina non
            // esiste non si elenca e non si dichiara come alternata.
            $predefinita = IndirizziPerLingua::linguaPredefinita();
            $indirizzi = [$predefinita => url('/'.ltrim($path, '/'))];

            foreach (IndirizziPerLingua::lingue() as $lingua) {
                // `lingue`: quelle in cui la pagina e' scritta (un prodotto
                // senza traduzione resta solo in italiano).
                if (isset($config['lingue']) && ! in_array($lingua, $config['lingue'], true)) {
                    continue;
                }

                if ($lingua !== $predefinita && ($indirizzo = IndirizziPerLingua::perPercorso($path, $lingua)) !== null) {
                    $indirizzi[$lingua] = $indirizzo;
                }
            }

            foreach ($indirizzi as $indirizzo) {
                $url = Url::create($indirizzo);

                if (count($indirizzi) > 1) {
                    foreach ($indirizzi as $lingua => $alternato) {
                        $url->addAlternate($alternato, $lingua);
                    }
                }

                if (isset($config['freq'])) {
                    $url->setChangeFrequency($config['freq']);
                }

                if (isset($config['priority'])) {
                    $url->setPriority($config['priority']);
                }

                if ($lastMod) {
                    $url->setLastModificationDate($lastMod);
                }

                foreach ($immagini as [$immagine, $titolo]) {
                    $url->addImage($immagine, title: $titolo);
                }

                $sitemap->add($url);
            }
        };

        $vuole = fn (string $sezione): bool => $parte === null || $parte === $sezione;

        if ($vuole('pagine')) {
            $this->pagine($addLocalizedUrls);
        }

        if ($vuole('notizie')) {
            Post::published()->with('media')->orderByDesc('published_at')->each(function (Post $post) use ($addLocalizedUrls): void {
                $copertina = $post->getFirstMediaUrl('cover');

                $addLocalizedUrls("/news/{$post->slug}", [
                    'freq' => Url::CHANGE_FREQUENCY_MONTHLY,
                    'priority' => 0.6,
                ], $post->updated_at, $copertina !== '' ? [[$copertina, (string) $post->getTranslation('title', 'it')]] : []);
            });
        }

        // A negozio chiuso la vetrina mostra la manutenzione: le schede non
        // si propongono a Google.
        if ($vuole('prodotti') && self::shopAperto()) {
            $this->prodotti($addLocalizedUrls);
        }

        // Le pagine delle atlete della prima squadra (stagione.atleta).
        if ($vuole('atlete')) {
            foreach ($this->atleteDellaPrimaSquadra() as $slug => $foto) {
                $addLocalizedUrls("/stagione/atleta/{$slug}", [
                    'freq' => Url::CHANGE_FREQUENCY_WEEKLY,
                    'priority' => 0.6,
                ], null, $foto !== null ? [[$foto, '']] : []);
            }
        }

        // Gli album della gallery, con le loro foto (al più 20 per album).
        if ($vuole('gallery')) {
            foreach ($this->albumDellaGallery() as $slug => $immagini) {
                $addLocalizedUrls("/gallery/album/{$slug}", [
                    'freq' => Url::CHANGE_FREQUENCY_MONTHLY,
                    'priority' => 0.5,
                ], null, $immagini);
            }
        }

        return $sitemap;
    }

    /**
     * Home, pagine fisse e pagine del CMS.
     */
    private function pagine(\Closure $addLocalizedUrls): void
    {
        $addLocalizedUrls('/', [
            'freq' => Url::CHANGE_FREQUENCY_DAILY,
            'priority' => 1.0,
        ]);

        $staticRoutes = [
            '/stagione' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.9],
            '/news' => ['freq' => Url::CHANGE_FREQUENCY_DAILY, 'priority' => 0.9],
            // Gli indirizzi veri, non i rimandi brevi (/risultati è un 302).
            '/stagione/risultati' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.8],
            '/stagione/classifica' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.8],
            '/gallery' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.7],
            '/diretta' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.6],
            '/staff' => ['freq' => Url::CHANGE_FREQUENCY_MONTHLY, 'priority' => 0.7],
            '/sponsor' => ['freq' => Url::CHANGE_FREQUENCY_MONTHLY, 'priority' => 0.7],
            '/shop' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.8],
            '/stagione/b1' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.7],
            '/stagione/u17' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.7],
            '/stagione/u15' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.7],
        ];

        foreach ($staticRoutes as $path => $config) {
            $addLocalizedUrls($path, $config);
        }

        Page::where('status', PostStatus::Published)->each(function (Page $page) use ($addLocalizedUrls): void {
            // L'indirizzo della sezione, non `/{slug}`: per le pagine di sezione
            // quello è un 301, e una sitemap di rimandi non serve a nessuno.
            $percorso = PageController::percorsoPubblico($page->slug);

            if ($percorso === null) {
                return;
            }

            $addLocalizedUrls($percorso, [
                'freq' => Url::CHANGE_FREQUENCY_MONTHLY,
                'priority' => 0.7,
            ], $page->updated_at);
        });
    }

    /**
     * Le schede dei prodotti in vendita e disponibili, con le foto. Un
     * prodotto esaurito resta fuori: la scheda esiste, ma non è da proporre.
     */
    private function prodotti(\Closure $addLocalizedUrls): void
    {
        Product::shoppable()
            ->with('media')
            ->withSum('variants', 'stock')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Product $prodotto) => $prodotto->availableStock() > 0)
            ->each(function (Product $prodotto) use ($addLocalizedUrls): void {
                $nome = (string) $prodotto->getTranslation('name', 'it');
                $immagini = $prodotto->getMedia('products')->concat($prodotto->getMedia('images'))
                    ->map(fn ($media) => $media->getUrl())
                    ->unique()
                    ->take(10)
                    ->map(fn (string $indirizzo) => [$indirizzo, $nome])
                    ->values()
                    ->all();

                $addLocalizedUrls(route('shop.product', ['product' => $prodotto->slug], false), [
                    'freq' => Url::CHANGE_FREQUENCY_WEEKLY,
                    'priority' => 0.6,
                    'lingue' => array_values(array_filter(IndirizziPerLingua::lingue(), fn (string $lingua) => $prodotto->tradottoIn($lingua))),
                ], $prodotto->updated_at, $immagini);
            });
    }

    /**
     * La sitemap delle notizie per Google News: solo gli articoli degli
     * ultimi due giorni, con `news:news`.
     */
    public function renderNotizie(): string
    {
        return Cache::remember(self::CACHE_KEY_NOTIZIE, self::CACHE_TTL_NOTIZIE, function (): string {
            $sitemap = Sitemap::create();

            Post::published()
                ->where('published_at', '>=', now()->subDays(2))
                ->orderByDesc('published_at')
                ->each(function (Post $post) use ($sitemap): void {
                    $sitemap->add(Url::create(url("/news/{$post->slug}"))
                        ->addNews(
                            DatiStrutturati::NOME,
                            'it',
                            (string) $post->getTranslation('title', 'it'),
                            $post->published_at ?? $post->created_at ?? now(),
                        ));
                });

            return $sitemap->render();
        });
    }

    /**
     * L'indice: le parti del sito e la sitemap di Google News. Il `lastmod`
     * di una parte è la modifica più recente dei suoi indirizzi; se non si
     * sa (cache vuota, nessuna data) non si scrive. Scritto a mano: `SitemapIndex` della libreria
     * mette a ogni voce l'ora della richiesta, che Google impara a ignorare.
     */
    public function renderIndice(): string
    {
        $voci = [];

        foreach (self::PARTI as $parte) {
            $modificata = Cache::get($this->chiave($parte).':modificata');
            $voci[route('sitemap.parte', ['parte' => $parte])] = is_int($modificata)
                ? Carbon::createFromTimestamp($modificata, config('app.timezone'))->format(DATE_ATOM)
                : null;
        }

        $voci[route('sitemap.notizie')] = null;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($voci as $indirizzo => $lastmod) {
            $xml .= '  <sitemap>'."\n"
                .'    <loc>'.htmlspecialchars($indirizzo, ENT_XML1).'</loc>'."\n"
                .($lastmod !== null ? '    <lastmod>'.$lastmod.'</lastmod>'."\n" : '')
                .'  </sitemap>'."\n";
        }

        return $xml.'</sitemapindex>'."\n";
    }

    /**
     * Slug e foto delle atlete in rosa nella prima squadra della stagione.
     *
     * @return array<string, ?string>
     */
    private function atleteDellaPrimaSquadra(): array
    {
        $stagione = Season::current()->latest('id')->first() ?? Season::latest('id')->first();
        $squadra = Team::where('is_internal', true)
            ->where(fn ($query) => $query->where('slug', 'savino-del-bene-volley')->orWhere('category', 'A1'))
            ->first();

        if (! $stagione || ! $squadra) {
            return [];
        }

        $atlete = [];

        Roster::with(['media', 'player.media'])
            ->whereHas('player')
            ->where('season_id', $stagione->id)
            ->where('team_id', $squadra->id)
            ->get()
            ->each(function (Roster $riga) use (&$atlete): void {
                $giocatrice = $riga->player;

                if ($giocatrice instanceof Player) {
                    $atlete[$giocatrice->id.'-'.Str::slug($giocatrice->full_name)] = $riga->official_photo_url ?: null;
                }
            });

        return $atlete;
    }

    /**
     * Gli album dell'archivio fotografico: slug `{id}-{titolo}` (come
     * `albumSlug()` del front-end) e le prime foto con il loro testo
     * alternativo, senza nomi di persone.
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    private function albumDellaGallery(): array
    {
        $album = [];

        foreach (app(GalleryArchive::class)->media(null, 'it') as $foto) {
            $id = (int) ($foto['event_id'] ?? 0);
            $indirizzo = (string) ($foto['url'] ?? '');

            if ($id <= 0 || $indirizzo === '') {
                continue;
            }

            $titolo = Str::slug((string) ($foto['event_name'] ?? ''));
            $slug = $titolo !== '' ? $id.'-'.$titolo : (string) $id;

            if (count($album[$slug] ?? []) < 20) {
                $album[$slug][] = [$indirizzo, (string) ($foto['alt'] ?? '')];
            }
        }

        return $album;
    }
}
