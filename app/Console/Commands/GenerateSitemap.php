<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Genera la sitemap.xml per il sito pubblico';

    public function handle(): int
    {
        $this->info('Generazione sitemap in corso...');

        $sitemap = Sitemap::create();

        $addLocalizedUrls = function ($path, $config = [], $lastMod = null) use ($sitemap) {
            $basePath = ltrim($path, '/');
            $itPath = '/'.$basePath;
            $enPath = '/en'.($basePath ? '/'.$basePath : '');

            $itUrl = Url::create($itPath)
                ->addAlternate(url($itPath), 'it')
                ->addAlternate(url($enPath), 'en');

            $enUrl = Url::create($enPath)
                ->addAlternate(url($itPath), 'it')
                ->addAlternate(url($enPath), 'en');

            if (isset($config['freq'])) {
                $itUrl->setChangeFrequency($config['freq']);
                $enUrl->setChangeFrequency($config['freq']);
            }
            if (isset($config['priority'])) {
                $itUrl->setPriority($config['priority']);
                $enUrl->setPriority($config['priority']);
            }
            if ($lastMod) {
                $itUrl->setLastModificationDate($lastMod);
                $enUrl->setLastModificationDate($lastMod);
            }

            $sitemap->add($itUrl);
            $sitemap->add($enUrl);
        };

        // Home page (priorità massima)
        $addLocalizedUrls('/', [
            'freq' => Url::CHANGE_FREQUENCY_DAILY,
            'priority' => 1.0,
        ]);

        // Rotte statiche principali
        $staticRoutes = [
            '/stagione' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.9],
            '/news' => ['freq' => Url::CHANGE_FREQUENCY_DAILY, 'priority' => 0.9],
            '/risultati' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.8],
            '/gallery' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.7],
            '/staff' => ['freq' => Url::CHANGE_FREQUENCY_MONTHLY, 'priority' => 0.7],
            '/sponsor' => ['freq' => Url::CHANGE_FREQUENCY_MONTHLY, 'priority' => 0.7],
            '/shop' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.8],
            '/stagione/b1' => ['freq' => Url::CHANGE_FREQUENCY_WEEKLY, 'priority' => 0.7],
        ];

        foreach ($staticRoutes as $path => $config) {
            $addLocalizedUrls($path, $config);
        }

        // Pagine CMS pubblicate
        Page::where('status', PostStatus::Published)->each(function (Page $page) use ($addLocalizedUrls) {
            $addLocalizedUrls("/{$page->slug}", [
                'freq' => Url::CHANGE_FREQUENCY_MONTHLY,
                'priority' => 0.7,
            ], $page->updated_at);
        });

        // Post/News pubblicati
        Post::published()->orderByDesc('published_at')->each(function (Post $post) use ($addLocalizedUrls) {
            $addLocalizedUrls("/news/{$post->slug}", [
                'freq' => Url::CHANGE_FREQUENCY_MONTHLY,
                'priority' => 0.6,
            ], $post->updated_at);
        });

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('✅ Sitemap generata con successo: public/sitemap.xml');

        return self::SUCCESS;
    }
}
