<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Page;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MigrateWpPages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wp:migrate-pages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legal pages from WooCommerce to CMS Pages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching pages from WP API...');

        // Define which pages to migrate by their slug
        $legalPagesSlugs = [
            'condizioni-di-spedizione',
            'informativa-sui-rimborsi',
            'condizioni-di-vendita-del-negozio-online',
            'privacy-e-cookies-policy',
            'condizioni-generali-di-vendita',
        ];

        $response = Http::get('https://shop.savinodelbenevolley.it/wp-json/wp/v2/pages', [
            'per_page' => 100,
        ]);

        if (! $response->successful()) {
            $this->error('Failed to fetch pages API.');

            return Command::FAILURE;
        }

        $pages = $response->json();
        $migrated = 0;

        foreach ($pages as $wpPage) {
            $slug = $wpPage['slug'];

            if (in_array($slug, $legalPagesSlugs)) {
                $this->line("Migrating page: {$slug}");

                Page::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'wp_id' => $wpPage['id'],
                        'title' => ['it' => html_entity_decode($wpPage['title']['rendered'])],
                        'content' => ['it' => $wpPage['content']['rendered']],
                        'excerpt' => ['it' => $wpPage['excerpt']['rendered'] ?? ''],
                        'status' => PostStatus::Published,
                        'author_id' => User::where('role', UserRole::SuperAdmin)->first()->id ?? 1,
                    ]
                );
                $migrated++;
            }
        }

        $this->info("Migrated $migrated legal pages successfully.");

        return Command::SUCCESS;
    }
}
