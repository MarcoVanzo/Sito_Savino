<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SiteSetting;
use App\Models\MenuItem;
use App\Models\HeroSlide;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Aggiorna SiteSettings principali
        $siteSettings = [
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

        foreach ($siteSettings as $key => $translations) {
            $setting = SiteSetting::where('key', $key)->first();
            if ($setting) {
                $setting->value = json_encode($translations);
                $setting->save();
            }
        }

        // Aggiorna le Statistiche
        $statsSetting = SiteSetting::where('key', 'stats')->first();
        if ($statsSetting && is_string($statsSetting->value)) {
            $stats = json_decode($statsSetting->value, true);
            if ($stats && isset($stats[0]['label']) && !isset($stats['it'])) {
                $translatedStats = [
                    'it' => [
                        ['value' => '40+', 'label' => 'Anni di Storia', 'icon' => '🏆'],
                        ['value' => '4.000+', 'label' => 'Posti al Palazzo Wanny', 'icon' => '🏟️'],
                        ['value' => 'A1', 'label' => 'Serie — Massima Divisione', 'icon' => '🏐'],
                        ['value' => 'CEV', 'label' => 'Champions League', 'icon' => '🌍']
                    ],
                    'en' => [
                        ['value' => '40+', 'label' => 'Years of History', 'icon' => '🏆'],
                        ['value' => '4.000+', 'label' => 'Seats at Palazzo Wanny', 'icon' => '🏟️'],
                        ['value' => 'A1', 'label' => 'Serie — Top Division', 'icon' => '🏐'],
                        ['value' => 'CEV', 'label' => 'Champions League', 'icon' => '🌍']
                    ]
                ];
                $statsSetting->value = json_encode($translatedStats, JSON_UNESCAPED_UNICODE);
                $statsSetting->save();
            }
        }

        // 2. Aggiorna Menu Items
        $menus = [
            'La Squadra' => 'The Team',
            'Roster' => 'Roster',
            'Risultati' => 'Results',
            'Gallery' => 'Gallery',
            'Staff' => 'Staff',
            'Il Club' => 'The Club',
            'La Società' => 'The Company',
            'Settore Giovanile' => 'Youth Academy',
            'Sponsor' => 'Sponsors',
            'News' => 'News',
            'Servizi' => 'Services',
            'Biglietteria' => 'Ticketing',
            'Shop Ufficiale' => 'Official Shop',
            'Contatti' => 'Contacts',
            'Comunicazione' => 'Communications',
        ];

        foreach (MenuItem::all() as $item) {
            $itLabel = $item->getTranslation('label', 'it', false);
            if ($itLabel && isset($menus[$itLabel])) {
                $item->setTranslation('label', 'en', $menus[$itLabel]);
                $item->save();
            }
        }

        // 3. Aggiorna Hero Slides
        $heroSlides = [
            'SAVINO DEL BENE VOLLEY' => [
                'subtitle' => ['it' => 'Scatena la Potenza.', 'en' => 'Unleash the Power.'],
                'cta_text' => ['it' => 'Scopri la Squadra', 'en' => 'Discover the Team'],
            ],
            'CAMPIONATO SERIE A1' => [
                'title' => ['it' => 'CAMPIONATO SERIE A1', 'en' => 'SERIE A1 LEAGUE'],
                'subtitle' => ['it' => 'Segui tutte le partite', 'en' => 'Follow all the matches'],
                'cta_text' => ['it' => 'Calendario', 'en' => 'Schedule'],
            ],
            'TUTTA LA POTENZA' => [
                'title' => ['it' => 'TUTTA LA POTENZA', 'en' => 'ALL THE POWER'],
                'subtitle' => ['it' => 'SCATENALA', 'en' => 'UNLEASH IT'],
                'cta_text' => ['it' => 'Acquista', 'en' => 'Shop Now'],
            ],
        ];

        foreach (HeroSlide::all() as $slide) {
            $itTitle = $slide->getTranslation('title', 'it', false);
            if ($itTitle && isset($heroSlides[$itTitle])) {
                $trans = $heroSlides[$itTitle];
                if (isset($trans['title'])) $slide->setTranslation('title', 'en', $trans['title']['en']);
                else $slide->setTranslation('title', 'en', $itTitle);
                
                $slide->setTranslation('subtitle', 'en', $trans['subtitle']['en']);
                $slide->setTranslation('cta_text', 'en', $trans['cta_text']['en']);
                $slide->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non reversibile automaticamente, e va bene così.
    }
};
