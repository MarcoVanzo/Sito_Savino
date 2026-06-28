<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MenuItem;
use Illuminate\Support\Facades\File;

class SeedMenuImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-menu-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seeds the menu images for production';

    /**
     * Execute the console command.
     */
    public function handle()
    {
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

        foreach ($items as $item) {
            if (isset($map[$item->label])) {
                $path = base_path('database/seeders/menu_images/' . $map[$item->label]);
                if (file_exists($path)) {
                    $item->clearMediaCollection('menu-images');
                    $item->addMedia($path)->preservingOriginal()->toMediaCollection('menu-images');
                    $this->info("Attached " . $map[$item->label] . " to " . $item->label);
                } else {
                    $this->error("File not found: $path");
                }
            }
        }
    }
}
