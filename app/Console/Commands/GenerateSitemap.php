<?php

namespace App\Console\Commands;

use App\Services\SitemapBuilder;
use Illuminate\Console\Command;

/**
 * Riscalda la cache della sitemap. La sitemap non è più un file su disco:
 * le sue parti le servono le rotte `/sitemap-{parte}.xml`, che leggono la
 * stessa cache.
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Ricalcola la sitemap del sito pubblico e ne aggiorna la cache';

    public function handle(SitemapBuilder $builder): int
    {
        $this->info('Generazione sitemap in corso...');

        foreach ($builder->refresh() as $parte => $xml) {
            $this->info("✅ sitemap-{$parte}.xml aggiornata in cache (".substr_count($xml, '<loc>').' URL).');
        }

        return self::SUCCESS;
    }
}
