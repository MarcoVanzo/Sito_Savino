<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use App\Models\Page;
use App\Models\Post;
use App\Enums\PostStatus;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Genera la sitemap.xml per il sito pubblico';

    public function handle(): int
    {
        $this->info('Generazione sitemap in corso...');

        $sitemap = Sitemap::create();

        // Home page (priorità massima)
        $sitemap->add(
            Url::create('/')
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0)
        );

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
            $sitemap->add(
                Url::create($path)
                    ->setChangeFrequency($config['freq'])
                    ->setPriority($config['priority'])
            );
        }

        // Pagine CMS pubblicate
        Page::where('status', PostStatus::Published)->each(function (Page $page) use ($sitemap) {
            $sitemap->add(
                Url::create("/{$page->slug}")
                    ->setLastModificationDate($page->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority(0.7)
            );
        });

        // Post/News pubblicati
        Post::published()->orderByDesc('published_at')->each(function (Post $post) use ($sitemap) {
            $sitemap->add(
                Url::create("/news/{$post->slug}")
                    ->setLastModificationDate($post->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority(0.6)
            );
        });

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('✅ Sitemap generata con successo: public/sitemap.xml');

        return self::SUCCESS;
    }
}
