<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Models\Post;
use App\Support\IndirizziPerLingua;
use Illuminate\Support\Facades\Cache;
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

    private const CACHE_TTL = 60 * 60 * 6;

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

        $addLocalizedUrls = function (string $path, array $config = [], $lastMod = null) use ($sitemap): void {
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

        Post::published()->orderByDesc('published_at')->each(function (Post $post) use ($addLocalizedUrls): void {
            $addLocalizedUrls("/news/{$post->slug}", [
                'freq' => Url::CHANGE_FREQUENCY_MONTHLY,
                'priority' => 0.6,
            ], $post->updated_at);
        });

        return $sitemap;
    }
}
