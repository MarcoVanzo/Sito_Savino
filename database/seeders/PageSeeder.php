<?php

namespace Database\Seeders;

use App\Models\Page;
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
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'template' => 'Public/ContentPage',
                'content' => '<h2>Informativa sulla Privacy</h2><p>Ai sensi dell\'art. 13 del Regolamento UE 2016/679 (GDPR), Savino Del Bene Volley informa che i dati personali raccolti tramite questo sito sono trattati nel rispetto della normativa vigente in materia di protezione dei dati personali.</p><h3>Titolare del Trattamento</h3><p>Savino Del Bene Volley S.S.D. a r.l. — Via di Scandicci, 50142 Firenze (FI)</p><h3>Dati raccolti</h3><p>Il sito raccoglie esclusivamente dati tecnici necessari alla navigazione (cookie tecnici, dati di sessione). Nessun dato di profilazione viene raccolto senza il consenso esplicito dell\'utente.</p><h3>Diritti dell\'interessato</h3><p>L\'utente può esercitare i diritti di cui agli artt. 15-22 del GDPR scrivendo a: privacy@savinodelbenevolley.com</p>',
            ],
            [
                'title' => 'Cookie Policy',
                'slug' => 'cookie-policy',
                'template' => 'Public/ContentPage',
                'content' => '<h2>Informativa sui Cookie</h2><p>Questo sito utilizza esclusivamente cookie tecnici necessari al funzionamento del sito web.</p><h3>Cookie tecnici</h3><p>Questi cookie sono essenziali per il corretto funzionamento del sito e non possono essere disabilitati. Includono cookie di sessione (XSRF-TOKEN, laravel_session) che garantiscono la sicurezza della navigazione.</p><h3>Cookie di terze parti</h3><p>Il sito non utilizza cookie di profilazione o di tracciamento di terze parti.</p><h3>Come gestire i cookie</h3><p>L\'utente può gestire le preferenze sui cookie attraverso le impostazioni del proprio browser.</p>',
            ],
        ];

        foreach ($pages as $pageData) {
            Page::firstOrCreate(
                ['slug' => $pageData['slug']],
                [
                    'title' => $pageData['title'],
                    'template' => $pageData['template'],
                    'status' => 'publish',
                    'content' => $pageData['content'] ?? null,
                ]
            );
        }
    }
}
