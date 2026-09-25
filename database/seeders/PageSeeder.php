<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Support\CondizioniDiVendita;
use App\Support\PagineLegaliDelloShop;
use App\Support\TestiDelleInformative;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['title' => 'Home', 'slug' => 'home', 'template' => 'Public/Home'],
            ['title' => 'Società', 'slug' => 'societa', 'template' => 'Public/Societa'],
            ['title' => 'Ticketing', 'slug' => 'ticketing', 'template' => 'Public/Ticketing'],
            ['title' => 'Sponsor', 'slug' => 'sponsor', 'template' => 'Public/Sponsor'],
            ['title' => 'Settore Giovanile', 'slug' => 'youth', 'template' => 'Public/Youth'],
            ['title' => 'Summer Camp', 'slug' => 'summer-camp', 'template' => 'Public/SummerCamp'],
            ['title' => 'Progetti Sociali', 'slug' => 'sociale', 'template' => 'Public/Sociale'],
            ['title' => 'Comunicazione', 'slug' => 'comunicazione', 'template' => 'Public/Comunicazione'],
            ['title' => 'Contatti', 'slug' => 'contatti', 'template' => 'Public/Contatti'],
            ['title' => 'Shop', 'slug' => 'shop', 'template' => 'Public/Shop'],
            ['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'template' => 'Public/ContentPage'],
            ['title' => 'Cookie Policy', 'slug' => 'cookie-policy', 'template' => 'Public/ContentPage'],
        ];

        foreach ($pages as $pageData) {
            $pagina = Page::firstOrCreate(
                ['slug' => $pageData['slug']],
                [
                    'title' => $pageData['title'],
                    'template' => $pageData['template'],
                    'status' => 'publish',
                ]
            );

            // Privacy Policy e Cookie Policy non hanno un testo cablato qui: lo
            // prendono dal file delle informative, insieme alla sua traduzione,
            // così un ambiente nuovo e il database dei test nascono con quella
            // pubblicata e non con una copia che invecchia nel seeder.
            if ($pagina->wasRecentlyCreated && $testi = TestiDelleInformative::contenuto($pageData['slug'])) {
                $pagina->setTranslations('content', $testi)->save();
            }
        }

        // Condizioni di vendita e recesso: stessa regola, testi dal loro file.
        CondizioniDiVendita::creaLePagineMancanti();

        // Spedizioni, resi e regolamento aste, dal vecchio negozio WooCommerce.
        PagineLegaliDelloShop::creaQuelleCheMancano();

        // Dichiarazione di accessibilita' (European Accessibility Act): la
        // crea la migrazione omonima, qui si ripete per gli ambienti nuovi.
        (require database_path('migrations/2026_09_26_100000_dichiarazione_di_accessibilita.php'))->up();
    }
}
