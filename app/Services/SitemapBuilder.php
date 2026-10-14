<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Models\Player;
use App\Models\Post;
use App\Models\Reindirizzamento;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use App\Services\Seo\DatiStrutturati;
use App\Support\IndirizziPerLingua;
use App\Support\RedirectGestiti;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
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

    private const CACHE_TTL = 60 * 60 * 6;

    private const CACHE_KEY_NOTIZIE = 'sitemap-news.xml';

    /** La sitemap delle notizie cambia con ogni articolo: mezz'ora. */
    private const CACHE_TTL_NOTIZIE = 60 * 30;

    /**
     * XML della sitemap, ricalcolato al più ogni sei ore.
     */
    public function render(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->build()->render());
    }

    /**
     * Ricalcola subito la sitemap e ne aggiorna la cache.
     */
    public function refresh(): string
    {
        $xml = $this->build()->render();

        Cache::put(self::CACHE_KEY, $xml, self::CACHE_TTL);

        return $xml;
    }

    public function build(): Sitemap
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

        Post::published()->with('media')->orderByDesc('published_at')->each(function (Post $post) use ($addLocalizedUrls): void {
            $copertina = $post->getFirstMediaUrl('cover');

            $addLocalizedUrls("/news/{$post->slug}", [
                'freq' => Url::CHANGE_FREQUENCY_MONTHLY,
                'priority' => 0.6,
            ], $post->updated_at, $copertina !== '' ? [[$copertina, (string) $post->getTranslation('title', 'it')]] : []);
        });

        // Le pagine delle atlete della prima squadra (stagione.atleta).
        foreach ($this->atleteDellaPrimaSquadra() as $slug => $foto) {
            $addLocalizedUrls("/stagione/atleta/{$slug}", [
                'freq' => Url::CHANGE_FREQUENCY_WEEKLY,
                'priority' => 0.6,
            ], null, $foto !== null ? [[$foto, '']] : []);
        }

        // Gli album della gallery, con le loro foto (al più 20 per album).
        foreach ($this->albumDellaGallery() as $slug => $immagini) {
            $addLocalizedUrls("/gallery/album/{$slug}", [
                'freq' => Url::CHANGE_FREQUENCY_MONTHLY,
                'priority' => 0.5,
            ], null, $immagini);
        }

        return $sitemap;
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
     * L'indice: la sitemap del sito e quella delle notizie.
     */
    public function renderIndice(): string
    {
        return SitemapIndex::create()
            ->add(url('/sitemap.xml'))
            ->add(url('/sitemap-news.xml'))
            ->render();
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
