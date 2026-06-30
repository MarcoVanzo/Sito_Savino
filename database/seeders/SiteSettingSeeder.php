<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── GENERAL ──────────────────────────────────────────────────
            [
                'key' => 'site_name',
                'value' => 'Savino Del Bene Volley',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Nome del Sito',
                'sort_order' => 0,
            ],
            [
                'key' => 'site_description',
                'value' => 'Sito ufficiale della Savino Del Bene Volley — Serie A1 femminile. Pallavolo, passione e tradizione dal 1982.',
                'type' => 'textarea',
                'group' => 'general',
                'label' => 'Descrizione del Sito',
                'sort_order' => 1,
            ],
            [
                'key' => 'site_logo',
                'value' => '/images/logo.png',
                'type' => 'image',
                'group' => 'general',
                'label' => 'Logo del Sito',
                'sort_order' => 2,
            ],
            [
                'key' => 'corporate_logo',
                'value' => '/images/logo-corporate.png',
                'type' => 'image',
                'group' => 'general',
                'label' => 'Logo Corporate',
                'sort_order' => 3,
            ],
            [
                'key' => 'corporate_url',
                'value' => 'https://www.savinodelbene.com/it/home/',
                'type' => 'url',
                'group' => 'general',
                'label' => 'URL Corporate',
                'sort_order' => 4,
            ],
            [
                'key' => 'corporate_name',
                'value' => 'Savino Del Bene',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Nome Corporate',
                'sort_order' => 5,
            ],
            [
                'key' => 'corporate_description',
                'value' => 'Global Logistics and Forwarding Company',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Descrizione Corporate',
                'sort_order' => 6,
            ],

            // ── CONTACT ──────────────────────────────────────────────────
            [
                'key' => 'contact_email',
                'value' => 'info@savinodelbenescandicci.it',
                'type' => 'email',
                'group' => 'contact',
                'label' => 'Email di Contatto',
                'sort_order' => 0,
            ],
            [
                'key' => 'contact_phone',
                'value' => '+39 055 XXX XXXX',
                'type' => 'phone',
                'group' => 'contact',
                'label' => 'Telefono',
                'sort_order' => 1,
            ],
            [
                'key' => 'contact_pec',
                'value' => 'savinodelbenevolley@pec.it',
                'type' => 'email',
                'group' => 'contact',
                'label' => 'PEC',
                'sort_order' => 2,
            ],
            [
                'key' => 'contact_address',
                'value' => 'Palazzo Wanny, Via Allende 10, Firenze',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Indirizzo',
                'sort_order' => 3,
            ],
            [
                'key' => 'contact_city',
                'value' => 'Scandicci (FI), Toscana',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Città',
                'sort_order' => 4,
            ],
            [
                'key' => 'office_hours',
                'value' => 'Lun-Ven: 09:00-18:00',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Orari Ufficio',
                'sort_order' => 5,
            ],
            [
                'key' => 'press_email',
                'value' => 'stampa@savinodelbenevolley.it',
                'type' => 'email',
                'group' => 'contact',
                'label' => 'Email Ufficio Stampa',
                'sort_order' => 6,
            ],
            [
                'key' => 'social_email',
                'value' => 'social@savinodelbenevolley.it',
                'type' => 'email',
                'group' => 'contact',
                'label' => 'Email Social Media',
                'sort_order' => 7,
            ],
            [
                'key' => 'media_email',
                'value' => 'media@savinodelbenevolley.it',
                'type' => 'email',
                'group' => 'contact',
                'label' => 'Email Media e Accrediti',
                'sort_order' => 8,
            ],
            [
                'key' => 'youth_email',
                'value' => 'giovanili@savinodelbenescandicci.it',
                'type' => 'email',
                'group' => 'contact',
                'label' => 'Email Settore Giovanile',
                'sort_order' => 9,
            ],

            // ── SOCIAL ───────────────────────────────────────────────────
            [
                'key' => 'social_instagram',
                'value' => 'https://www.instagram.com/savinodelbenevolley/',
                'type' => 'url',
                'group' => 'social',
                'label' => 'Instagram',
                'sort_order' => 0,
            ],
            [
                'key' => 'social_facebook',
                'value' => 'https://www.facebook.com/savinodelbenevolley',
                'type' => 'url',
                'group' => 'social',
                'label' => 'Facebook',
                'sort_order' => 1,
            ],
            [
                'key' => 'social_youtube',
                'value' => 'https://www.youtube.com/@savinodelbenevolley1771',
                'type' => 'url',
                'group' => 'social',
                'label' => 'YouTube',
                'sort_order' => 2,
            ],
            [
                'key' => 'social_x',
                'value' => '',
                'type' => 'url',
                'group' => 'social',
                'label' => 'X (Twitter)',
                'sort_order' => 3,
            ],
            [
                'key' => 'social_tiktok',
                'value' => '',
                'type' => 'url',
                'group' => 'social',
                'label' => 'TikTok',
                'sort_order' => 4,
            ],

            // ── FOOTER ──────────────────────────────────────────────────
            [
                'key' => 'footer_tagline',
                'value' => 'Dal 1982, una tradizione di eccellenza nella pallavolo femminile italiana. Serie A1 — Palazzo Wanny, Firenze.',
                'type' => 'textarea',
                'group' => 'footer',
                'label' => 'Tagline Footer',
                'sort_order' => 0,
            ],
            [
                'key' => 'footer_copyright',
                'value' => '© {year} Savino Del Bene Volley — Tutti i diritti riservati.',
                'type' => 'text',
                'group' => 'footer',
                'label' => 'Copyright Footer',
                'sort_order' => 1,
            ],
            [
                'key' => 'footer_piva',
                'value' => '',
                'type' => 'text',
                'group' => 'footer',
                'label' => 'Partita IVA',
                'sort_order' => 2,
            ],

            // ── SEO ──────────────────────────────────────────────────────
            [
                'key' => 'seo_default_title',
                'value' => 'Savino Del Bene Volley — Sito Ufficiale',
                'type' => 'text',
                'group' => 'seo',
                'label' => 'Titolo SEO Predefinito',
                'sort_order' => 0,
            ],
            [
                'key' => 'seo_default_description',
                'value' => 'Sito ufficiale della Savino Del Bene Volley, squadra di Serie A1 femminile di pallavolo. Scopri roster, risultati, ticketing e news dal Palazzo Wanny di Firenze.',
                'type' => 'textarea',
                'group' => 'seo',
                'label' => 'Descrizione SEO Predefinita',
                'sort_order' => 1,
            ],
            [
                'key' => 'seo_og_image',
                'value' => '/images/logo.png',
                'type' => 'image',
                'group' => 'seo',
                'label' => 'Immagine OG Predefinita',
                'sort_order' => 2,
            ],

            // ── APPEARANCE ───────────────────────────────────────────────
            [
                'key' => 'primary_color',
                'value' => '#C5A55A',
                'type' => 'color',
                'group' => 'appearance',
                'label' => 'Colore Primario',
                'sort_order' => 0,
            ],
            [
                'key' => 'secondary_color',
                'value' => '#0B1521',
                'type' => 'color',
                'group' => 'appearance',
                'label' => 'Colore Secondario',
                'sort_order' => 1,
            ],

            // ── HOME ─────────────────────────────────────────────────────
            [
                'key' => 'home_hero_title',
                'value' => 'SAVINO DEL BENE',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Titolo Hero Homepage',
                'sort_order' => 0,
            ],
            [
                'key' => 'home_hero_title_accent',
                'value' => 'VOLLEY',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Titolo Hero Accento',
                'sort_order' => 1,
            ],
            [
                'key' => 'home_hero_claim',
                'value' => 'Scatena la Potenza.',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Claim Hero Homepage',
                'sort_order' => 2,
            ],
            [
                'key' => 'home_cta_primary_text',
                'value' => 'Prossima Partita',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Testo CTA Primario',
                'sort_order' => 3,
            ],
            [
                'key' => 'home_cta_primary_url',
                'value' => '/stagione',
                'type' => 'url',
                'group' => 'home',
                'label' => 'URL CTA Primario',
                'sort_order' => 4,
            ],
            [
                'key' => 'home_cta_secondary_text',
                'value' => 'Biglietteria',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Testo CTA Secondario',
                'sort_order' => 5,
            ],
            [
                'key' => 'home_cta_secondary_url',
                'value' => '/ticketing',
                'type' => 'url',
                'group' => 'home',
                'label' => 'URL CTA Secondario',
                'sort_order' => 6,
            ],
            [
                'key' => 'home_stats',
                'value' => json_encode([
                    ['value' => '40+', 'label' => 'Anni di Storia', 'icon' => '🏆'],
                    ['value' => '4.000+', 'label' => 'Posti al Palazzo Wanny', 'icon' => '🏟️'],
                    ['value' => 'A1', 'label' => 'Serie — Massima Divisione', 'icon' => '🏐'],
                    ['value' => 'CEV', 'label' => 'Champions League', 'icon' => '🌍'],
                ]),
                'type' => 'json',
                'group' => 'home',
                'label' => 'Statistiche Homepage',
                'sort_order' => 7,
            ],
            [
                'key' => 'home_cta_ticketing_title',
                'value' => 'Biglietteria',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Titolo CTA Biglietteria',
                'sort_order' => 8,
            ],
            [
                'key' => 'home_cta_ticketing_text',
                'value' => 'Acquista i biglietti per la prossima partita',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Testo CTA Biglietteria',
                'sort_order' => 9,
            ],
            [
                'key' => 'home_cta_ticketing_url',
                'value' => '/ticketing',
                'type' => 'url',
                'group' => 'home',
                'label' => 'URL CTA Biglietteria',
                'sort_order' => 10,
            ],
            [
                'key' => 'home_cta_shop_title',
                'value' => 'Shop Ufficiale',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Titolo CTA Shop',
                'sort_order' => 11,
            ],
            [
                'key' => 'home_cta_shop_text',
                'value' => 'Maglie, merchandise e accessori della squadra',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Testo CTA Shop',
                'sort_order' => 12,
            ],
            [
                'key' => 'home_cta_shop_url',
                'value' => '/shop',
                'type' => 'url',
                'group' => 'home',
                'label' => 'URL CTA Shop',
                'sort_order' => 13,
            ],
        ];

        foreach ($settings as $setting) {
            SiteSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'group' => $setting['group'],
                    'label' => $setting['label'],
                    'sort_order' => $setting['sort_order'],
                ]
            );
        }
    }
}
