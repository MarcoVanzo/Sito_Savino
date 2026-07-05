<?php

use App\Models\MenuItem;
use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Add English translations to MenuItem translatable fields
     * and SiteSetting values for the English locale.
     */
    public function up(): void
    {
        // ── MENU ITEMS: Add EN translations ──────────────────────────

        $menuTranslations = [
            // Main menu parents: label → [motto_title_en, motto_subtitle_en, label_en]
            'Stagione' => [
                'label' => 'Season',
                'motto_title' => 'The Team',
                'motto_subtitle' => 'Roster, staff and results of the current season',
            ],
            'Società' => [
                'label' => 'Club',
                'motto_title' => 'The Club',
                'motto_subtitle' => 'History, organisation and facilities of Savino Del Bene',
            ],
            'Ticketing' => [
                'label' => 'Ticketing',
                'motto_title' => 'Feel the Emotion',
                'motto_subtitle' => 'Tickets, season passes and match information',
            ],
            'Sponsor' => [
                'label' => 'Sponsors',
                'motto_title' => 'Our Partners',
                'motto_subtitle' => 'A network of excellence supporting the team',
            ],
            'SDB Youth' => [
                'label' => 'SDB Youth',
                'motto_title' => 'The Future',
                'motto_subtitle' => 'Youth academy, B1 and talent scouting',
            ],
            'Summer Camp' => [
                'label' => 'Summer Camp',
                'motto_title' => 'Summer of Sport',
                'motto_subtitle' => 'Summer camps and sports experiences for young people',
            ],
            'Sociale' => [
                'label' => 'Community',
                'motto_title' => 'Sport & Society',
                'motto_subtitle' => 'Social projects, inclusion and sustainability',
            ],
            'Comunicazione' => [
                'label' => 'Media',
                'motto_title' => 'Press Area',
                'motto_subtitle' => 'News, press releases, accreditation and media materials',
            ],
            'Shop Ufficiale' => [
                'label' => 'Official Shop',
                'motto_title' => 'Official Shop',
                'motto_subtitle' => 'Jerseys, merchandise and team accessories',
            ],
        ];

        // Children: label_it => label_en, description_en
        $childTranslations = [
            // Stagione children
            'Serie A1' => ['label' => 'Serie A1', 'description' => 'Roster and Staff'],
            'Foto Ufficiale' => ['label' => 'Official Photo', 'description' => 'PDF Download'],
            'Classifica e Risultati' => ['label' => 'Standings & Results', 'description' => null],
            'CEV Champions League' => ['label' => 'CEV Champions League', 'description' => 'Standings and Group'],
            'Coppa Italia & Playoff' => ['label' => 'Cup & Playoffs', 'description' => 'Results Bracket'],
            'News & Comunicati' => ['label' => 'News & Press', 'description' => null],
            'Foto Gallery' => ['label' => 'Photo Gallery', 'description' => 'Photo Gallery'],
            // Società children
            'Organigramma' => ['label' => 'Organisation', 'description' => 'Board members'],
            'Storia' => ['label' => 'History', 'description' => null],
            'Safeguarding' => ['label' => 'Safeguarding', 'description' => 'Downloadable PDF'],
            'Contatti' => ['label' => 'Contacts', 'description' => null],
            'Palazzetto & Google Maps' => ['label' => 'Arena & Directions', 'description' => 'How to reach us'],
            // Ticketing children
            'Biglietteria' => ['label' => 'Tickets', 'description' => '- Vivaticket WL'],
            'Campagna Abbonamenti' => ['label' => 'Season Passes', 'description' => null],
            'Accessibilità & Info' => ['label' => 'Accessibility & Info', 'description' => 'Disabled, Dogs, Guests'],
            'Convenzioni' => ['label' => 'Partnerships', 'description' => 'Subscribers and holders'],
            // Sponsor children
            'I Nostri Sponsor' => ['label' => 'Our Sponsors', 'description' => 'Logos and Categories'],
            'Diventa Sponsor' => ['label' => 'Become a Sponsor', 'description' => 'Benefits & LinkedIn'],
            'Title Sponsor (SDB)' => ['label' => 'Title Sponsor (SDB)', 'description' => 'Vision, Mission, Subsidiaries'],
            'Hospitality' => ['label' => 'Hospitality', 'description' => 'Service Description'],
            // Youth children
            'Serie B1 / U19' => ['label' => 'Serie B1 / U19', 'description' => 'Roster and Staff'],
            'Serie U17 & U15' => ['label' => 'U17 & U15', 'description' => 'Roster and Staff'],
            'Settore Giovanile' => ['label' => 'Youth Academy', 'description' => 'Youth team photos'],
            'Talent Day & Recruiting' => ['label' => 'Talent Day & Recruiting', 'description' => 'Calendar and Form'],
            'Progetto Affiliazioni' => ['label' => 'Affiliations Project', 'description' => 'Club Logos'],
            // Summer Camp children
            'Tutte le Info' => ['label' => 'All Information', 'description' => null],
            'Iscrizione (Experience)' => ['label' => 'Registration (Experience)', 'description' => 'Multi-step form'],
            // Sociale children
            'Progetti Sociali' => ['label' => 'Social Projects', 'description' => null],
            'Volley 4 All' => ['label' => 'Volley 4 All', 'description' => 'AllunaMente Partner'],
            'Bilancio Sostenibilità' => ['label' => 'Sustainability Report', 'description' => 'Season PDF'],
            'Progetto Scuola' => ['label' => 'School Project', 'description' => 'Schools and Partners'],
            'Aste Benefiche' => ['label' => 'Charity Auctions', 'description' => '-> E-Shop'],
            // Comunicazione children
            'Accrediti Stampa' => ['label' => 'Press Accreditation', 'description' => 'Request Form'],
            'Cartelle Stampa' => ['label' => 'Press Folders', 'description' => 'Materials'],
            'Magazine' => ['label' => 'Magazine', 'description' => 'Online PDF'],
            'Double Face' => ['label' => 'Double Face', 'description' => '-> YouTube Channel'],
            // Shop children
            'Kit Gara' => ['label' => 'Match Kits', 'description' => 'Home, Away, Champions'],
            'Abbigliamento & Accessori' => ['label' => 'Apparel & Accessories', 'description' => 'Catalogue'],
            'Aste & Outlet' => ['label' => 'Auctions & Outlet', 'description' => 'Deadlines and Bids'],
            'Guida Taglie & Contatti' => ['label' => 'Size Guide & Contacts', 'description' => 'Errea File'],
            // Footer parents
            'Il Club' => ['label' => 'The Club'],
            'Servizi' => ['label' => 'Services'],
            // Footer children
            'Roster A1' => ['label' => 'Serie A1 Roster'],
            'Risultati' => ['label' => 'Results'],
            'Gallery' => ['label' => 'Gallery'],
            'Staff Tecnico' => ['label' => 'Coaching Staff'],
            'La Società' => ['label' => 'About Us'],
            'News' => ['label' => 'News'],
        ];

        // Apply main menu translations
        foreach ($menuTranslations as $italianLabel => $translations) {
            $items = MenuItem::all()->filter(function ($item) use ($italianLabel) {
                $label = $item->getTranslation('label', 'it', false);
                return $label === $italianLabel;
            });

            foreach ($items as $item) {
                foreach ($translations as $field => $enValue) {
                    $item->setTranslation($field, 'en', $enValue);
                }
                $item->saveQuietly();
            }
        }

        // Apply child translations
        foreach ($childTranslations as $italianLabel => $translations) {
            $items = MenuItem::all()->filter(function ($item) use ($italianLabel) {
                $label = $item->getTranslation('label', 'it', false);
                return $label === $italianLabel;
            });

            foreach ($items as $item) {
                foreach ($translations as $field => $enValue) {
                    if ($enValue !== null) {
                        $item->setTranslation($field, 'en', $enValue);
                    }
                }
                $item->saveQuietly();
            }
        }

        // ── SITE SETTINGS: Add EN translations ──────────────────────

        $settingTranslations = [
            'hero_tagline' => ['it' => 'Scatena la Potenza.', 'en' => 'Unleash the Power.'],
            'hero_cta1_label' => ['it' => 'Prossima Partita', 'en' => 'Next Match'],
            'hero_cta2_label' => ['it' => 'Biglietteria', 'en' => 'Ticketing'],
            'cta_ticketing_title' => ['it' => 'Biglietteria', 'en' => 'Ticketing'],
            'cta_ticketing_text' => ['it' => 'Acquista i biglietti per la prossima partita', 'en' => 'Buy tickets for the next match'],
            'cta_shop_title' => ['it' => 'Shop Ufficiale', 'en' => 'Official Shop'],
            'cta_shop_text' => ['it' => 'Maglie, merchandise e accessori della squadra', 'en' => 'Jerseys, merchandise and team accessories'],
            'stats_title' => ['it' => 'Il Club in Numeri', 'en' => 'The Club in Numbers'],
            'stats_subtitle' => ['it' => 'I Numeri', 'en' => 'The Numbers'],
        ];

        foreach ($settingTranslations as $key => $translations) {
            $setting = SiteSetting::where('key', $key)->first();
            if ($setting) {
                $setting->value = json_encode($translations);
                $setting->save();
            }
        }

        // Stats JSON — special handling (array of objects)
        $statsSetting = SiteSetting::where('key', 'stats')->first();
        if ($statsSetting) {
            $statsValue = [
                'it' => [
                    ['value' => '40+', 'label' => 'Anni di Storia', 'icon' => '🏆'],
                    ['value' => '4.000+', 'label' => 'Posti al Palazzo Wanny', 'icon' => '🏟️'],
                    ['value' => 'A1', 'label' => 'Serie — Massima Divisione', 'icon' => '🏐'],
                    ['value' => 'CEV', 'label' => 'Champions League', 'icon' => '🌍'],
                ],
                'en' => [
                    ['value' => '40+', 'label' => 'Years of History', 'icon' => '🏆'],
                    ['value' => '4,000+', 'label' => 'Seats at Palazzo Wanny', 'icon' => '🏟️'],
                    ['value' => 'A1', 'label' => 'Serie — Top Division', 'icon' => '🏐'],
                    ['value' => 'CEV', 'label' => 'Champions League', 'icon' => '🌍'],
                ],
            ];
            $statsSetting->value = json_encode($statsValue);
            $statsSetting->save();
        }

        // Clear all caches
        SiteSetting::clearCache();
        Cache::forget('menu_tree_it');
        Cache::forget('menu_tree_en');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove EN translations from menu items
        $menuItems = MenuItem::all();
        foreach ($menuItems as $item) {
            foreach (['label', 'description', 'motto_title', 'motto_subtitle'] as $field) {
                $item->forgetTranslation($field, 'en');
            }
            $item->saveQuietly();
        }

        // Restore SiteSetting values to plain Italian strings
        $settingsToRevert = [
            'hero_tagline' => 'Scatena la Potenza.',
            'hero_cta1_label' => 'Prossima Partita',
            'hero_cta2_label' => 'Biglietteria',
            'cta_ticketing_title' => 'Biglietteria',
            'cta_ticketing_text' => 'Acquista i biglietti per la prossima partita',
            'cta_shop_title' => 'Shop Ufficiale',
            'cta_shop_text' => 'Maglie, merchandise e accessori della squadra',
            'stats_title' => 'Il Club in Numeri',
            'stats_subtitle' => 'I Numeri',
        ];

        foreach ($settingsToRevert as $key => $value) {
            SiteSetting::where('key', $key)->update(['value' => $value]);
        }

        $statsSetting = SiteSetting::where('key', 'stats')->first();
        if ($statsSetting) {
            $statsSetting->value = json_encode([
                ['value' => '40+', 'label' => 'Anni di Storia', 'icon' => '🏆'],
                ['value' => '4.000+', 'label' => 'Posti al Palazzo Wanny', 'icon' => '🏟️'],
                ['value' => 'A1', 'label' => 'Serie — Massima Divisione', 'icon' => '🏐'],
                ['value' => 'CEV', 'label' => 'Champions League', 'icon' => '🌍'],
            ]);
            $statsSetting->save();
        }

        SiteSetting::clearCache();
        Cache::forget('menu_tree_it');
        Cache::forget('menu_tree_en');
    }
};
