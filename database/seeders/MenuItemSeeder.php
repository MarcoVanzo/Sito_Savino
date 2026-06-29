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
            'label' => 'Stagione',
            'url' => '/stagione/',
            'location' => 'main',
            'sort_order' => 0,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'La Squadra',
            'motto_subtitle' => 'Roster, staff e risultati della stagione in corso',
        ]);
        $this->createChildren($stagione, [
            ['label' => 'Serie A1', 'url' => '/stagione/', 'description' => 'Roster e Staff'],
            ['label' => 'Foto Ufficiale', 'url' => '/stagione/foto-ufficiale/', 'description' => 'Download PDF'],
            ['label' => 'Classifica e Risultati', 'url' => '/stagione/risultati/', 'description' => '/stagione/risultati/'],
            ['label' => 'CEV Champions League', 'url' => '/stagione/cev/', 'description' => 'Classifiche e Girone'],
            ['label' => 'Coppa Italia & Playoff', 'url' => '/stagione/coppa-italia/', 'description' => 'Tabellone Risultati'],
            ['label' => 'News & Comunicati', 'url' => '/stagione/news/', 'description' => '/stagione/news/'],
            ['label' => 'Foto Gallery', 'url' => '/gallery', 'description' => 'Galleria Fotografica'],
        ]);

        // 2. Società
        $societa = MenuItem::create([
            'label' => 'Società',
            'url' => '/societa/',
            'location' => 'main',
            'sort_order' => 1,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Il Club',
            'motto_subtitle' => 'Storia, organigramma e strutture della Savino Del Bene',
        ]);
        $this->createChildren($societa, [
            ['label' => 'Organigramma', 'url' => '/societa/organigramma/', 'description' => 'Cariche e nominativi'],
            ['label' => 'Storia', 'url' => '/societa/storia/', 'description' => '/societa/storia/'],
            ['label' => 'Safeguarding', 'url' => '/societa/safeguarding/', 'description' => 'PDF Scaricabile'],
            ['label' => 'Contatti', 'url' => '/societa/contatti/', 'description' => '/societa/contatti/'],
            ['label' => 'Palazzetto & Google Maps', 'url' => '/societa/palazzetto/', 'description' => 'Come raggiungerci'],
        ]);

        // 3. Ticketing
        $ticketing = MenuItem::create([
            'label' => 'Ticketing',
            'url' => '/ticketing/',
            'location' => 'main',
            'sort_order' => 2,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Vivi l\'Emozione',
            'motto_subtitle' => 'Biglietti, abbonamenti e informazioni per le partite',
        ]);
        $this->createChildren($ticketing, [
            ['label' => 'Biglietteria', 'url' => '/ticketing/', 'description' => '- Vivaticket WL'],
            ['label' => 'Campagna Abbonamenti', 'url' => '/ticketing/abbonamenti/', 'description' => '/ticketing/abbonamenti/'],
            ['label' => 'Accessibilità & Info', 'url' => '/ticketing/accessibilita/', 'description' => 'Disabili, Cani, Ospiti'],
            ['label' => 'Convenzioni', 'url' => '/ticketing/convenzioni/', 'description' => 'Abbonati e possessori'],
        ]);

        // 4. Sponsor
        $sponsor = MenuItem::create([
            'label' => 'Sponsor',
            'url' => '/sponsor/',
            'location' => 'main',
            'sort_order' => 3,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'I Partner',
            'motto_subtitle' => 'Un network di eccellenza al fianco della squadra',
        ]);
        $this->createChildren($sponsor, [
            ['label' => 'I Nostri Sponsor', 'url' => '/sponsor/nostri-sponsor/', 'description' => 'Loghi e Categorie'],
            ['label' => 'Diventa Sponsor', 'url' => '/sponsor/diventa-sponsor/', 'description' => 'Vantaggi & LinkedIn'],
            ['label' => 'Title Sponsor (SDB)', 'url' => '/sponsor/title-sponsor/', 'description' => 'Vision, Mission, Consociate'],
            ['label' => 'Hospitality', 'url' => '/sponsor/hospitality/', 'description' => 'Descrizione Servizio'],
        ]);

        // 5. SDB Youth
        $youth = MenuItem::create([
            'label' => 'SDB Youth',
            'url' => '/youth/',
            'location' => 'main',
            'sort_order' => 4,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Il Futuro',
            'motto_subtitle' => 'Settore giovanile, B1 e talent scouting',
        ]);
        $this->createChildren($youth, [
            ['label' => 'Serie B1 / U19', 'url' => '/youth/b1-u19/', 'description' => 'Roster e Staff'],
            ['label' => 'Serie U17 & U15', 'url' => '/youth/u17-u15/', 'description' => 'Roster e Staff'],
            ['label' => 'Settore Giovanile', 'url' => '/youth/settore-giovanile/', 'description' => 'Foto squadre di base'],
            ['label' => 'Talent Day & Recruiting', 'url' => '/youth/talent-day/', 'description' => 'Calendario e Form'],
            ['label' => 'Progetto Affiliazioni', 'url' => '/youth/affiliazioni/', 'description' => 'Loghi Società'],
        ]);

        // 6. Camp
        $camp = MenuItem::create([
            'label' => 'Summer Camp',
            'url' => '/summer-camp/',
            'location' => 'main',
            'sort_order' => 5,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Estate di Sport',
            'motto_subtitle' => 'Summer camp e esperienze sportive per ragazzi',
        ]);
        $this->createChildren($camp, [
            ['label' => 'Tutte le Info', 'url' => '/summer-camp/info/', 'description' => '/summer-camp/info/'],
            ['label' => 'Iscrizione (Experience)', 'url' => '/summer-camp/iscrizione/', 'description' => 'Form multi-step'],
        ]);

        // 7. Sociale
        $sociale = MenuItem::create([
            'label' => 'Sociale',
            'url' => '/sociale/',
            'location' => 'main',
            'sort_order' => 6,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Sport e Società',
            'motto_subtitle' => 'Progetti sociali, inclusione e sostenibilità',
        ]);
        $this->createChildren($sociale, [
            ['label' => 'Progetti Sociali', 'url' => '/sociale/progetti/', 'description' => '/sociale/progetti/'],
            ['label' => 'Volley 4 All', 'url' => '/sociale/volley-4-all/', 'description' => 'Partner AllunaMente'],
            ['label' => 'Bilancio Sostenibilità', 'url' => '/sociale/sostenibilita/', 'description' => 'PDF Stagioni'],
            ['label' => 'Progetto Scuola', 'url' => '/sociale/progetto-scuola/', 'description' => 'Istituti e Partner'],
            ['label' => 'Aste Benefiche', 'url' => '/sociale/aste/', 'description' => '-> E-Shop'],
        ]);

        // 8. Media
        $media = MenuItem::create([
            'label' => 'Comunicazione',
            'url' => '/comunicazione/',
            'location' => 'main',
            'sort_order' => 7,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Area Stampa',
            'motto_subtitle' => 'News, comunicati, accrediti e materiale media',
        ]);
        $this->createChildren($media, [
            ['label' => 'Accrediti Stampa', 'url' => '/comunicazione/accrediti/', 'description' => 'Form Richiesta'],
            ['label' => 'Cartelle Stampa', 'url' => '/comunicazione/cartelle/', 'description' => 'Materiali'],
            ['label' => 'Magazine', 'url' => '/comunicazione/magazine/', 'description' => 'PDF Online'],
            ['label' => 'Double Face', 'url' => '/comunicazione/double-face/', 'description' => '-> YouTube Channel'],
        ]);

        // 9. Shop
        $shop = MenuItem::create([
            'label' => 'Shop Ufficiale',
            'url' => '/shop/',
            'location' => 'main',
            'sort_order' => 8,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Shop Ufficiale',
            'motto_subtitle' => 'Maglie, merchandise e accessori della squadra',
        ]);
        $this->createChildren($shop, [
            ['label' => 'Kit Gara', 'url' => '/shop/kit-gara/', 'description' => 'Home, Away, Champions'],
            ['label' => 'Abbigliamento & Accessori', 'url' => '/shop/abbigliamento/', 'description' => 'Catalogo'],
            ['label' => 'Aste & Outlet', 'url' => '/shop/outlet/', 'description' => 'Scadenze e Rilanci'],
            ['label' => 'Guida Taglie & Contatti', 'url' => '/shop/guida-taglie/', 'description' => 'File Errea'],
        ]);

        // ── FOOTER MENU ──────────────────────────────────────────────

        // 1. Stagione
        $footerStagione = MenuItem::create([
            'label' => 'Stagione',
            'url' => '/stagione',
            'location' => 'footer',
            'sort_order' => 0,
            'is_active' => true,
            'is_highlight' => false,
        ]);
        $this->createChildren($footerStagione, [
            ['label' => 'Roster A1', 'url' => '/stagione'],
            ['label' => 'Risultati', 'url' => '/risultati'],
            ['label' => 'Gallery', 'url' => '/gallery'],
            ['label' => 'Staff Tecnico', 'url' => '/staff'],
        ], 'footer');

        // 2. Il Club
        $footerClub = MenuItem::create([
            'label' => 'Il Club',
            'url' => '/societa',
            'location' => 'footer',
            'sort_order' => 1,
            'is_active' => true,
            'is_highlight' => false,
        ]);
        $this->createChildren($footerClub, [
            ['label' => 'La Società', 'url' => '/societa'],
            ['label' => 'Settore Giovanile', 'url' => '/youth'],
            ['label' => 'Sponsor', 'url' => '/sponsor'],
            ['label' => 'News', 'url' => '/news'],
        ], 'footer');

        // 3. Servizi
        $footerServizi = MenuItem::create([
            'label' => 'Servizi',
            'url' => '/ticketing',
            'location' => 'footer',
            'sort_order' => 2,
            'is_active' => true,
            'is_highlight' => false,
        ]);
        $this->createChildren($footerServizi, [
            ['label' => 'Biglietteria', 'url' => '/ticketing'],
            ['label' => 'Shop Ufficiale', 'url' => '/shop'],
            ['label' => 'Contatti', 'url' => '/contatti'],
            ['label' => 'Comunicazione', 'url' => '/comunicazione'],
        ], 'footer');
    }

    /**
     * Create child menu items for a given parent.
     */
    private function createChildren(MenuItem $parent, array $children, string $location = 'main'): void
    {
        foreach ($children as $index => $child) {
            MenuItem::create([
                'label' => $child['label'],
                'url' => $child['url'],
                'description' => $child['description'] ?? null,
                'parent_id' => $parent->id,
                'location' => $location,
                'sort_order' => $index,
                'is_active' => true,
                'is_highlight' => $child['is_highlight'] ?? false,
            ]);
        }
    }
}
