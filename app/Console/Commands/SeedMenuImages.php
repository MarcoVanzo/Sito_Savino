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

        // Stessa mappa del fallback statico, per non doverne tenere allineate
        // due: le chiavi precedenti ("Camp", "Media", "Shop") non
        // corrispondevano più a nessuna voce, e quelle tre restavano senza
        // immagine in media library.
        //
        // Sono i WebP a 720px, non i JPEG originali: finiscono nel riquadro da
        // ~290px del mega-menu, e a piena risoluzione erano 26 MB complessivi —
        // con ticketing.jpg da solo a 9,5 MB — scaricati dal visitatore.
        $map = MenuItem::$staticMenuImages;

        // Solo il menu principale: è l'unico che mostra le immagini. Il footer
        // ha voci con le stesse label e caricarcele sopra è lavoro sprecato.
        $items = MenuItem::where('location', 'main')->whereNull('parent_id')->get();
        $this->info("Found {$items->count()} parent menu items");

        foreach ($items as $item) {
            $label = mb_strtolower(trim($item->getTranslation('label', 'it', false) ?: $item->label));

            if (isset($map[$label])) {
                $path = base_path('database/seeders/menu_images/'.$map[$label]);
                if (file_exists($path)) {
                    try {
                        // Niente clearMediaCollection prima del caricamento: la
                        // collection è singleFile(), quindi addMedia sostituisce
                        // già l'immagine precedente. Svuotarla in anticipo
                        // significa perderla se poi il caricamento fallisce —
                        // com'è successo con le credenziali Spaces scadute, che
                        // hanno lasciato tutte le voci senza immagine.
                        $item->addMedia($path)
                            ->preservingOriginal()
                            ->toMediaCollection('menu-images', $disk);
                        $url = $item->getFirstMediaUrl('menu-images');
                        $this->info("✓ {$item->label} → {$url}");
                    } catch (\Throwable $e) {
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
