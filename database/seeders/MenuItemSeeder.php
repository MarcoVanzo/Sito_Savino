<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate the table to start fresh (clears cache via model events)
        Schema::disableForeignKeyConstraints();
        MenuItem::truncate();
        Schema::enableForeignKeyConstraints();

        // ── MAIN MENU ────────────────────────────────────────────────

        // 1. Stagione
        $stagione = MenuItem::create([
            'label' => ['it' => 'Stagione', 'en' => 'Season'],
            'url' => '/stagione/',
            'location' => 'main',
            'sort_order' => 0,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => ['it' => 'La Squadra', 'en' => 'The Team'],
            'motto_subtitle' => [
                'it' => 'Roster, staff e risultati della stagione in corso',
                'en' => 'Roster, staff and results from the current season',
            ],
        ]);
        $this->createChildren($stagione, [
            [
                'label' => ['it' => 'Serie A1', 'en' => 'Serie A1'],
                'url' => '/stagione/',
                'description' => ['it' => 'Roster e Staff', 'en' => 'Roster and staff'],
            ],
            [
                'label' => ['it' => 'Foto Ufficiale', 'en' => 'Official Team Photo'],
                'url' => '/stagione/foto-ufficiale/',
                'description' => ['it' => 'Download PDF', 'en' => 'PDF download'],
            ],
            [
                'label' => ['it' => 'Risultati', 'en' => 'Results'],
                'url' => '/stagione/risultati/',
                'description' => ['it' => 'Calendario e tabellini', 'en' => 'Fixtures and box scores'],
            ],
            [
                'label' => ['it' => 'Classifica', 'en' => 'Standings'],
                'url' => '/stagione/classifica/',
            ],
            [
                'label' => ['it' => 'CEV Champions League', 'en' => 'CEV Champions League'],
                'url' => '/stagione/cev/',
                'description' => ['it' => 'Classifiche e Girone', 'en' => 'Standings and pool'],
            ],
            [
                'label' => ['it' => 'Coppa Italia & Playoff', 'en' => 'Coppa Italia & Playoffs'],
                'url' => '/stagione/coppa-italia/',
                'description' => ['it' => 'Tabellone Risultati', 'en' => 'Results bracket'],
            ],
            [
                'label' => ['it' => 'News & Comunicati', 'en' => 'News & Press Releases'],
                'url' => '/stagione/news/',
                'description' => ['it' => 'Notizie e comunicati', 'en' => 'News and press releases'],
            ],
            [
                'label' => ['it' => 'Foto Gallery', 'en' => 'Photo Gallery'],
                'url' => '/gallery',
                'description' => ['it' => 'Galleria Fotografica', 'en' => 'Photo gallery'],
            ],
        ]);

        // 2. Società
        $societa = MenuItem::create([
            'label' => ['it' => 'Società', 'en' => 'Club'],
            'url' => '/societa/',
            'location' => 'main',
            'sort_order' => 1,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => ['it' => 'Il Club', 'en' => 'The Club'],
            'motto_subtitle' => [
                'it' => 'Storia, organigramma e strutture della Savino Del Bene',
                'en' => 'History, leadership and facilities of Savino Del Bene',
            ],
        ]);
        $this->createChildren($societa, [
            [
                'label' => ['it' => 'Organigramma', 'en' => 'Leadership'],
                'url' => '/societa/organigramma/',
                'description' => ['it' => 'Cariche e nominativi', 'en' => 'Roles and names'],
            ],
            [
                'label' => ['it' => 'Storia', 'en' => 'History'],
                'url' => '/societa/storia/',
                'description' => ['it' => 'Dal 1982 a oggi', 'en' => 'From 1982 to today'],
            ],
            [
                'label' => ['it' => 'Safeguarding', 'en' => 'Safeguarding'],
                'url' => '/societa/safeguarding/',
                'description' => ['it' => 'PDF Scaricabile', 'en' => 'Downloadable PDF'],
            ],
            [
                'label' => ['it' => 'Contatti', 'en' => 'Contact'],
                'url' => '/societa/contatti/',
                'description' => ['it' => 'Recapiti e sede', 'en' => 'Contact details and address'],
            ],
            [
                'label' => ['it' => 'Palazzetto & Google Maps', 'en' => 'Arena & Google Maps'],
                'url' => '/societa/palazzetto/',
                'description' => ['it' => 'Come raggiungerci', 'en' => 'How to reach us'],
            ],
        ]);

        // 3. Ticketing
        $ticketing = MenuItem::create([
            'label' => ['it' => 'Ticketing', 'en' => 'Tickets'],
            'url' => '/ticketing/',
            'location' => 'main',
            'sort_order' => 2,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => ['it' => 'Vivi l\'Emozione', 'en' => 'Feel the Thrill'],
            'motto_subtitle' => [
                'it' => 'Biglietti, abbonamenti e informazioni per le partite',
                'en' => 'Tickets, season passes and match information',
            ],
        ]);
        $this->createChildren($ticketing, [
            [
                'label' => ['it' => 'Biglietteria', 'en' => 'Box Office'],
                'url' => '/ticketing/',
                'description' => ['it' => 'Prezzi e punti vendita', 'en' => 'Prices and points of sale'],
            ],
            [
                'label' => ['it' => 'Campagna Abbonamenti', 'en' => 'Season Ticket Campaign'],
                'url' => '/ticketing/abbonamenti/',
                'description' => ['it' => 'Formule e prezzi', 'en' => 'Packages and prices'],
            ],
            [
                'label' => ['it' => 'Accessibilità & Info', 'en' => 'Accessibility & Info'],
                'url' => '/ticketing/accessibilita/',
                'description' => ['it' => 'Disabili, Cani, Ospiti', 'en' => 'Accessible seating, dogs, away fans'],
            ],
            [
                'label' => ['it' => 'Convenzioni', 'en' => 'Discounts'],
                'url' => '/ticketing/convenzioni/',
                'description' => ['it' => 'Abbonati e possessori', 'en' => 'Season ticket and card holders'],
            ],
        ]);

        // 4. Sponsor
        $sponsor = MenuItem::create([
            'label' => ['it' => 'Sponsor', 'en' => 'Sponsors'],
            'url' => '/sponsor/',
            'location' => 'main',
            'sort_order' => 3,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => ['it' => 'I Partner', 'en' => 'Our Partners'],
            'motto_subtitle' => [
                'it' => 'Un network di eccellenza al fianco della squadra',
                'en' => 'A network of excellence alongside the team',
            ],
        ]);
        $this->createChildren($sponsor, [
            [
                'label' => ['it' => 'I Nostri Sponsor', 'en' => 'Our Sponsors'],
                'url' => '/sponsor/nostri-sponsor/',
                'description' => ['it' => 'Loghi e Categorie', 'en' => 'Logos and categories'],
            ],
            [
                'label' => ['it' => 'Diventa Sponsor', 'en' => 'Become a Sponsor'],
                'url' => '/sponsor/diventa-sponsor/',
                'description' => ['it' => 'Vantaggi & LinkedIn', 'en' => 'Benefits & LinkedIn'],
            ],
            [
                'label' => ['it' => 'Title Sponsor (SDB)', 'en' => 'Title Sponsor (SDB)'],
                'url' => '/sponsor/title-sponsor/',
                'description' => ['it' => 'Vision, Mission, Consociate', 'en' => 'Vision, mission, group companies'],
            ],
            [
                'label' => ['it' => 'Hospitality', 'en' => 'Hospitality'],
                'url' => '/sponsor/hospitality/',
                'description' => ['it' => 'Descrizione Servizio', 'en' => 'Service details'],
            ],
        ]);

        // 5. SDB Youth
        $youth = MenuItem::create([
            'label' => ['it' => 'SDB Youth', 'en' => 'SDB Youth'],
            'url' => '/youth/',
            'location' => 'main',
            'sort_order' => 4,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => ['it' => 'Il Futuro', 'en' => 'The Future'],
            'motto_subtitle' => [
                'it' => 'Settore giovanile, B1 e talent scouting',
                'en' => 'Youth programme, Serie B1 and talent scouting',
            ],
        ]);
        $this->createChildren($youth, [
            [
                'label' => ['it' => 'Serie B1 / U19', 'en' => 'Serie B1 / U19'],
                'url' => '/youth/b1-u19/',
                'description' => ['it' => 'Roster e Staff', 'en' => 'Roster and staff'],
            ],
            [
                'label' => ['it' => 'Serie U17 & U15', 'en' => 'U17 & U15'],
                'url' => '/youth/u17-u15/',
                'description' => ['it' => 'Roster e Staff', 'en' => 'Roster and staff'],
            ],
            [
                'label' => ['it' => 'Settore Giovanile', 'en' => 'Youth Programme'],
                'url' => '/youth/settore-giovanile/',
                'description' => ['it' => 'Foto squadre di base', 'en' => 'Grassroots team photos'],
            ],
            [
                'label' => ['it' => 'Talent Day & Recruiting', 'en' => 'Talent Day & Recruiting'],
                'url' => '/youth/talent-day/',
                'description' => ['it' => 'Calendario e Form', 'en' => 'Dates and entry form'],
            ],
            [
                'label' => ['it' => 'Progetto Affiliazioni', 'en' => 'Affiliated Clubs Project'],
                'url' => '/youth/affiliazioni/',
                'description' => ['it' => 'Loghi Società', 'en' => 'Club logos'],
            ],
        ]);

        // 6. Camp
        $camp = MenuItem::create([
            'label' => ['it' => 'Summer Camp', 'en' => 'Summer Camp'],
            'url' => '/summer-camp/',
            'location' => 'main',
            'sort_order' => 5,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => ['it' => 'Estate di Sport', 'en' => 'A Summer of Sport'],
            'motto_subtitle' => [
                'it' => 'Summer camp e esperienze sportive per ragazzi',
                'en' => 'Summer camps and sporting experiences for young players',
            ],
        ]);
        $this->createChildren($camp, [
            [
                'label' => ['it' => 'Tutte le Info', 'en' => 'All the Info'],
                'url' => '/summer-camp/info/',
                'description' => ['it' => 'Programma e informazioni', 'en' => 'Programme and information'],
            ],
            [
                'label' => ['it' => 'Iscrizione (Experience)', 'en' => 'Registration (Experience)'],
                'url' => '/summer-camp/iscrizione/',
                'description' => ['it' => 'Form multi-step', 'en' => 'Multi-step form'],
            ],
        ]);

        // 7. Sociale
        $sociale = MenuItem::create([
            'label' => ['it' => 'Sociale', 'en' => 'Community'],
            'url' => '/sociale/',
            'location' => 'main',
            'sort_order' => 6,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => ['it' => 'Sport e Società', 'en' => 'Sport and Society'],
            'motto_subtitle' => [
                'it' => 'Progetti sociali, inclusione e sostenibilità',
                'en' => 'Community projects, inclusion and sustainability',
            ],
        ]);
        $this->createChildren($sociale, [
            [
                'label' => ['it' => 'Progetti Sociali', 'en' => 'Community Projects'],
                'url' => '/sociale/progetti/',
                'description' => ['it' => 'Inclusione e territorio', 'en' => 'Inclusion and community'],
            ],
            [
                'label' => ['it' => 'Volley 4 All', 'en' => 'Volley 4 All'],
                'url' => '/sociale/volley-4-all/',
                'description' => ['it' => 'Partner AllunaMente', 'en' => 'AllunaMente partnership'],
            ],
            [
                'label' => ['it' => 'Bilancio Sostenibilità', 'en' => 'Sustainability Report'],
                'url' => '/sociale/sostenibilita/',
                'description' => ['it' => 'PDF Stagioni', 'en' => 'PDFs by season'],
            ],
            [
                'label' => ['it' => 'Progetto Scuola', 'en' => 'Schools Project'],
                'url' => '/sociale/progetto-scuola/',
                'description' => ['it' => 'Istituti e Partner', 'en' => 'Schools and partners'],
            ],
            [
                'label' => ['it' => 'Aste Benefiche', 'en' => 'Charity Auctions'],
                'url' => '/sociale/aste/',
                'description' => ['it' => "Aste sull'e-shop ufficiale", 'en' => 'Auctions on the official e-shop'],
            ],
        ]);

        // 8. Media
        $media = MenuItem::create([
            'label' => ['it' => 'Comunicazione', 'en' => 'Media'],
            'url' => '/comunicazione/',
            'location' => 'main',
            'sort_order' => 7,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => ['it' => 'Area Stampa', 'en' => 'Press Area'],
            'motto_subtitle' => [
                'it' => 'News, comunicati, accrediti e materiale media',
                'en' => 'News, press releases, accreditation and media resources',
            ],
        ]);
        $this->createChildren($media, [
            [
                'label' => ['it' => 'Accrediti Stampa', 'en' => 'Press Accreditation'],
                'url' => '/comunicazione/accrediti/',
                'description' => ['it' => 'Form Richiesta', 'en' => 'Request form'],
            ],
            [
                'label' => ['it' => 'Cartelle Stampa', 'en' => 'Press Kits'],
                'url' => '/comunicazione/cartelle/',
                'description' => ['it' => 'Materiali', 'en' => 'Downloads'],
            ],
            [
                'label' => ['it' => 'Magazine', 'en' => 'Magazine'],
                'url' => '/comunicazione/magazine/',
                'description' => ['it' => 'PDF Online', 'en' => 'Online PDF'],
            ],
            [
                'label' => ['it' => 'Double Face', 'en' => 'Double Face'],
                'url' => '/comunicazione/double-face/',
                'description' => ['it' => 'Il magazine ufficiale', 'en' => 'The official magazine'],
            ],
        ]);

        // 9. Shop
        $shop = MenuItem::create([
            // Etichetta corta: questa voce e' il pulsante pieno della testata.
            'label' => ['it' => 'Shop', 'en' => 'Shop'],
            'url' => '/shop/',
            'location' => 'main',
            'sort_order' => 8,
            'is_active' => true,
            // Evidenziata = fuori dalla barra, dentro il pulsante pieno della testata.
            'is_highlight' => true,
            'motto_title' => ['it' => 'Shop Ufficiale', 'en' => 'Official Shop'],
            'motto_subtitle' => ['it' => 'Maglie, merchandise e accessori della squadra', 'en' => 'Team shirts, merchandise and accessories'],
        ]);
        $this->createChildren($shop, [
            [
                'label' => ['it' => 'Kit Gara', 'en' => 'Match Kits'],
                'url' => '/shop/categoria/kit-gara-25-26',
                'description' => ['it' => 'Home, Away, Champions', 'en' => 'Home, Away, Champions'],
            ],
            [
                'label' => ['it' => 'Abbigliamento & Accessori', 'en' => 'Apparel & Accessories'],
                'url' => '/shop/categoria/abbigliamento',
                'description' => ['it' => 'Catalogo', 'en' => 'Catalogue'],
            ],
            [
                'label' => ['it' => 'Aste & Outlet', 'en' => 'Auctions & Outlet'],
                'url' => '/shop/aste',
                'description' => ['it' => 'Scadenze e Rilanci', 'en' => 'Deadlines and Bids'],
            ],
            [
                'label' => ['it' => 'Guida Taglie', 'en' => 'Size Guide'],
                'url' => '/shop/guida-taglie',
                'description' => ['it' => 'File Errea', 'en' => 'Errea File'],
            ],
            [
                'label' => ['it' => 'Contatti Shop', 'en' => 'Shop Contacts'],
                'url' => '/shop/contatti',
                'description' => ['it' => 'Assistenza Clienti', 'en' => 'Customer Support'],
            ],
        ]);

        // ── FOOTER MENU ──────────────────────────────────────────────

        // 1. Stagione
        $footerStagione = MenuItem::create([
            'label' => ['it' => 'Stagione', 'en' => 'Season'],
            'url' => '/stagione',
            'location' => 'footer',
            'sort_order' => 0,
            'is_active' => true,
            'is_highlight' => false,
        ]);
        $this->createChildren($footerStagione, [
            ['label' => ['it' => 'Roster A1', 'en' => 'A1 Roster'], 'url' => '/stagione'],
            ['label' => ['it' => 'Risultati', 'en' => 'Results'], 'url' => '/risultati'],
            ['label' => ['it' => 'Gallery', 'en' => 'Gallery'], 'url' => '/gallery'],
            ['label' => ['it' => 'Staff Tecnico', 'en' => 'Coaching Staff'], 'url' => '/staff'],
        ], 'footer');

        // 2. Il Club
        $footerClub = MenuItem::create([
            'label' => ['it' => 'Il Club', 'en' => 'The Club'],
            'url' => '/societa',
            'location' => 'footer',
            'sort_order' => 1,
            'is_active' => true,
            'is_highlight' => false,
        ]);
        $this->createChildren($footerClub, [
            ['label' => ['it' => 'La Società', 'en' => 'The Club'], 'url' => '/societa'],
            ['label' => ['it' => 'Settore Giovanile', 'en' => 'Youth Programme'], 'url' => '/youth'],
            ['label' => ['it' => 'Sponsor', 'en' => 'Sponsors'], 'url' => '/sponsor'],
            ['label' => ['it' => 'News', 'en' => 'News'], 'url' => '/news'],
        ], 'footer');

        // 3. Servizi
        $footerServizi = MenuItem::create([
            'label' => ['it' => 'Servizi', 'en' => 'Services'],
            'url' => '/ticketing',
            'location' => 'footer',
            'sort_order' => 2,
            'is_active' => true,
            'is_highlight' => false,
        ]);
        $this->createChildren($footerServizi, [
            ['label' => ['it' => 'Biglietteria', 'en' => 'Box Office'], 'url' => '/ticketing'],
            ['label' => ['it' => 'Shop Ufficiale', 'en' => 'Official Shop'], 'url' => '/shop'],
            ['label' => ['it' => 'Contatti', 'en' => 'Contact'], 'url' => '/contatti'],
            ['label' => ['it' => 'Comunicazione', 'en' => 'Media'], 'url' => '/comunicazione'],
        ], 'footer');
    }

    /**
     * Create child menu items for a given parent.
     */
    private function createChildren(MenuItem $parent, array $children, string $location = 'main'): void
    {
        foreach ($children as $index => $child) {
            $attributes = [
                'label' => $child['label'],
                'url' => $child['url'],
                'parent_id' => $parent->id,
                'location' => $location,
                'sort_order' => $index,
                'is_active' => true,
                'is_highlight' => $child['is_highlight'] ?? false,
            ];

            // Senza descrizione la colonna resta NULL: passando null a un campo
            // translatable si otterrebbe {"it":null}, che non è una descrizione
            // ma nemmeno l'assenza di una, e il fallback del front-end
            // (common.explore_section) si aspetta l'assenza.
            if (isset($child['description'])) {
                $attributes['description'] = $child['description'];
            }

            MenuItem::create($attributes);
        }
    }
}
