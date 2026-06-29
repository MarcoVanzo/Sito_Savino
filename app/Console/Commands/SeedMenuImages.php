<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use Illuminate\Console\Command;

class SeedMenuImages extends Command
{
    protected $signature = 'app:seed-menu-images';

    protected $description = 'Seeds the menu images for production';

    public function handle()
    {
        $disk = config('media-library.disk_name', 'public');
        $this->info("Using disk: {$disk}");

        $map = [
            'Stagione' => 'stagione.jpg',
            'Società' => 'societa.jpg',
            'Ticketing' => 'ticketing.jpg',
            'Sponsor' => 'sponsor.jpg',
            'SDB Youth' => 'youth.jpg',
            'Camp' => 'camp.jpg',
            'Sociale' => 'sociale.jpg',
            'Media' => 'media.jpg',
            'Shop' => 'shop.jpg',
        ];

        $items = MenuItem::whereNull('parent_id')->get();
        $this->info("Found {$items->count()} parent menu items");

        foreach ($items as $item) {
            if (isset($map[$item->label])) {
                $path = base_path('database/seeders/menu_images/'.$map[$item->label]);
                if (file_exists($path)) {
                    try {
                        $item->clearMediaCollection('menu-images');
                        $item->addMedia($path)
                            ->preservingOriginal()
                            ->toMediaCollection('menu-images', $disk);
                        $url = $item->getFirstMediaUrl('menu-images');
                        $this->info("✓ {$item->label} → {$url}");
                    } catch (\Exception $e) {
                        $this->error("✗ {$item->label}: ".$e->getMessage());
                    }
                } else {
                    $this->error("File not found: $path");
                }
            }
        }

        MenuItem::clearCache();
        $this->info('Menu cache cleared.');
    }
}
