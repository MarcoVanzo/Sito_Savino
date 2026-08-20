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
                'key' => 'site_logo',
                'value' => '',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Logo del sito',
                'sort_order' => 2,
            ],
            [
                'key' => 'corporate_logo',
                'value' => '/images/logo-corporate-left.png',
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
                'key' => 'email',
                'value' => 'info@savinodelbenevolley.it',
                'type' => 'email',
                'group' => 'contact',
                'label' => 'Email di Contatto',
                'sort_order' => 0,
            ],
            [
                'key' => 'phone',
                'value' => '055 721503',
                'type' => 'phone',
                'group' => 'contact',
                'label' => 'Telefono',
                'sort_order' => 1,
            ],
            [
                'key' => 'pec',
                'value' => 'savinodelbenevolley@pec.it',
                'type' => 'email',
                'group' => 'contact',
                'label' => 'PEC',
                'sort_order' => 2,
            ],
            [
                'key' => 'address',
                'value' => 'Via Benozzo Gozzoli, 5/6 — 50018 Scandicci (FI)',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Indirizzo',
                'sort_order' => 3,
            ],
            [
                'key' => 'city',
                'value' => 'Scandicci (FI)',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Città',
                'sort_order' => 4,
            ],
            [
                'key' => 'office_hours',
                // L'unico recapito fatto di parole, quindi tradotto: gli altri
                // (email, indirizzo, partite IVA) sono uguali in ogni lingua.
                'value' => '{"it":"Lun-Ven: 09:00-18:00","en":"Mon-Fri: 09:00-18:00"}',
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
            [
                'key' => 'legal_piva',
                'value' => '06271460484',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Partita IVA',
                'sort_order' => 10,
            ],
            [
                'key' => 'legal_cf',
                'value' => '94217750481',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Codice Fiscale',
                'sort_order' => 11,
            ],
            [
                'key' => 'legal_fipav',
                'value' => '100470331',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Codice FIPAV',
                'sort_order' => 12,
            ],
            [
                'key' => 'legal_sdi',
                'value' => 'KRRH6B9',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Codice SDI',
                'sort_order' => 13,
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
                'value' => 'https://www.youtube.com/channel/UCyHpswavR-Rs6ssmF4BvLCQ',
                'type' => 'url',
                'group' => 'social',
                'label' => 'YouTube',
                'sort_order' => 2,
            ],
            [
                'key' => 'social_x',
                'value' => 'https://x.com/sdbvolley',
                'type' => 'url',
                'group' => 'social',
                'label' => 'X (Twitter)',
                'sort_order' => 3,
            ],
            [
                'key' => 'social_tiktok',
                'value' => 'https://www.tiktok.com/@savinodelbenescandicci',
                'type' => 'url',
                'group' => 'social',
                'label' => 'TikTok',
                'sort_order' => 4,
            ],
            [
                'key' => 'social_linkedin',
                'value' => 'https://www.linkedin.com/company/savino-del-bene-volley/',
                'type' => 'url',
                'group' => 'social',
                'label' => 'LinkedIn',
                'sort_order' => 5,
            ],
            [
                'key' => 'social_whatsapp',
                'value' => 'https://whatsapp.com/channel/0029VasgCCu3WHTcri3MjL2W',
                'type' => 'url',
                'group' => 'social',
                'label' => 'Canale WhatsApp',
                'sort_order' => 6,
            ],

            // ── FOOTER ──────────────────────────────────────────────────
            [
                'key' => 'footer_tagline',
                'value' => 'Dal 1982, una tradizione di eccellenza nella pallavolo femminile italiana. Serie A1 — Pala BigMat, Firenze.',
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

            // ── HOME ─────────────────────────────────────────────────────
            [
                'key' => 'hero_title',
                'value' => 'SAVINO DEL BENE',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Titolo Hero Homepage',
                'sort_order' => 0,
            ],
            [
                'key' => 'hero_subtitle',
                'value' => 'VOLLEY',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Titolo Hero Accento',
                'sort_order' => 1,
            ],
            [
                'key' => 'hero_tagline',
                'value' => 'Scatena la Potenza.',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Claim Hero Homepage',
                'sort_order' => 2,
            ],
            [
                'key' => 'hero_cta1_label',
                'value' => 'Prossima Partita',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Testo CTA Primario',
                'sort_order' => 3,
            ],
            [
                'key' => 'hero_cta1_url',
                'value' => '/stagione',
                'type' => 'url',
                'group' => 'home',
                'label' => 'URL CTA Primario',
                'sort_order' => 4,
            ],
            [
                'key' => 'hero_cta2_label',
                'value' => 'Biglietteria',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Testo CTA Secondario',
                'sort_order' => 5,
            ],
            [
                'key' => 'hero_cta2_url',
                'value' => '/ticketing',
                'type' => 'url',
                'group' => 'home',
                'label' => 'URL CTA Secondario',
                'sort_order' => 6,
            ],
            [
                'key' => 'stats',
                'value' => json_encode([
                    ['value' => '40+', 'label' => 'Anni di Storia', 'icon' => '🏆'],
                    ['value' => '3.500+', 'label' => 'Posti al Pala BigMat', 'icon' => '🏟️'],
                    ['value' => 'A1', 'label' => 'Serie — Massima Divisione', 'icon' => '🏐'],
                    ['value' => 'CEV', 'label' => 'Champions League', 'icon' => '🌍'],
                ]),
                'type' => 'json',
                'group' => 'home',
                'label' => 'Statistiche Homepage',
                'sort_order' => 7,
            ],
            [
                'key' => 'cta_ticketing_title',
                'value' => 'Biglietteria',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Titolo CTA Biglietteria',
                'sort_order' => 8,
            ],
            [
                'key' => 'cta_ticketing_text',
                'value' => 'Acquista i biglietti per la prossima partita',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Testo CTA Biglietteria',
                'sort_order' => 9,
            ],
            [
                'key' => 'cta_ticketing_url',
                'value' => '/ticketing',
                'type' => 'url',
                'group' => 'home',
                'label' => 'URL CTA Biglietteria',
                'sort_order' => 10,
            ],
            [
                'key' => 'cta_shop_title',
                'value' => 'Shop Ufficiale',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Titolo CTA Shop',
                'sort_order' => 11,
            ],
            [
                'key' => 'cta_shop_text',
                'value' => 'Maglie, merchandise e accessori della squadra',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Testo CTA Shop',
                'sort_order' => 12,
            ],
            [
                'key' => 'cta_shop_url',
                'value' => '/shop',
                'type' => 'url',
                'group' => 'home',
                'label' => 'URL CTA Shop',
                'sort_order' => 13,
            ],
            [
                'key' => 'stats_title',
                'value' => 'Il Club in Numeri',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Titolo Sezione Statistiche',
                'sort_order' => 14,
            ],
            [
                'key' => 'stats_subtitle',
                'value' => 'I Numeri',
                'type' => 'text',
                'group' => 'home',
                'label' => 'Sottotitolo Sezione Statistiche',
                'sort_order' => 15,
            ],
        ];

        foreach ($settings as $setting) {
            $existing = SiteSetting::where('key', $setting['key'])->first();
            if ($existing) {
                // Preserviamo il valore modificato dall'utente, aggiorniamo solo i metadati strutturali
                $existing->update([
                    'type' => $setting['type'],
                    'group' => $setting['group'],
                    'label' => $setting['label'],
                    'sort_order' => $setting['sort_order'],
                ]);
            } else {
                SiteSetting::create($setting);
            }
        }
    }
}
