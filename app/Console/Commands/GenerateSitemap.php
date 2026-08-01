<?php

namespace App\Console\Commands;

use App\Services\SitemapBuilder;
use Illuminate\Console\Command;

/**
 * Riscalda la cache della sitemap. La sitemap non è più un file su disco:
 * viene servita dalla rotta `/sitemap.xml`, che legge la stessa cache.
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Ricalcola la sitemap del sito pubblico e ne aggiorna la cache';

    public function handle(SitemapBuilder $builder): int
    {
        $this->info('Generazione sitemap in corso...');

        $xml = $builder->refresh();

        $urls = substr_count($xml, '<loc>');

        $this->info("✅ Sitemap aggiornata in cache ({$urls} URL).");

        return self::SUCCESS;
    }
}
