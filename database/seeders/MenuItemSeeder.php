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
            'url' => '/stagione',
            'location' => 'main',
            'sort_order' => 0,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'La Squadra',
            'motto_subtitle' => 'Roster, staff e risultati della stagione in corso',
        ]);
        $this->createChildren($stagione, [
            ['label' => 'Roster A1', 'url' => '/stagione'],
            ['label' => 'Staff Tecnico', 'url' => '/staff'],
            ['label' => 'Risultati', 'url' => '/risultati'],
            ['label' => 'Gallery', 'url' => '/gallery'],
        ]);

        // 2. Società
        $societa = MenuItem::create([
            'label' => 'Società',
            'url' => '/societa',
            'location' => 'main',
            'sort_order' => 1,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Il Club',
            'motto_subtitle' => 'Storia, organigramma e strutture della Savino Del Bene',
        ]);
        $this->createChildren($societa, [
            ['label' => 'Storia', 'url' => '/societa'],
            ['label' => 'Organigramma', 'url' => '/societa#organigramma'],
            ['label' => 'Palazzetto', 'url' => '/societa#palazzetto'],
            ['label' => 'Contatti', 'url' => '/contatti'],
        ]);

        // 3. Ticketing
        $ticketing = MenuItem::create([
            'label' => 'Ticketing',
            'url' => '/ticketing',
            'location' => 'main',
            'sort_order' => 2,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Vivi l\'Emozione',
            'motto_subtitle' => 'Biglietti, abbonamenti e informazioni per le partite',
        ]);
        $this->createChildren($ticketing, [
            ['label' => 'Biglietteria', 'url' => '/ticketing'],
            ['label' => 'Abbonamenti', 'url' => '/ticketing#abbonamenti'],
            ['label' => 'Convenzioni', 'url' => '/ticketing#convenzioni'],
            ['label' => 'Accessibilità', 'url' => '/ticketing#accessibilita'],
        ]);

        // 4. Sponsor
        $sponsor = MenuItem::create([
            'label' => 'Sponsor',
            'url' => '/sponsor',
            'location' => 'main',
            'sort_order' => 3,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'I Partner',
            'motto_subtitle' => 'Un network di eccellenza al fianco della squadra',
        ]);
        $this->createChildren($sponsor, [
            ['label' => 'I Nostri Sponsor', 'url' => '/sponsor'],
            ['label' => 'Title Sponsor', 'url' => '/sponsor#title'],
            ['label' => 'Diventa Sponsor', 'url' => '/sponsor#diventa-sponsor'],
            ['label' => 'Hospitality', 'url' => '/sponsor#hospitality'],
            ['label' => 'Affiliazioni', 'url' => '/sponsor#affiliazioni'],
        ]);

        // 5. SDB Youth
        $youth = MenuItem::create([
            'label' => 'SDB Youth',
            'url' => '/youth',
            'location' => 'main',
            'sort_order' => 4,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Il Futuro',
            'motto_subtitle' => 'Settore giovanile, B1 e talent scouting',
        ]);
        $this->createChildren($youth, [
            ['label' => 'Roster B1', 'url' => '/stagione/b1'],
            ['label' => 'Settore Giovanile', 'url' => '/youth'],
            ['label' => 'Talent Day', 'url' => '/youth#talent-day'],
            ['label' => 'Progetto Scuola', 'url' => '/youth#progetto-scuola'],
        ]);

        // 6. Camp
        $camp = MenuItem::create([
            'label' => 'Camp',
            'url' => '/summer-camp',
            'location' => 'main',
            'sort_order' => 5,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Estate di Sport',
            'motto_subtitle' => 'Summer camp e esperienze sportive per ragazzi',
        ]);
        $this->createChildren($camp, [
            ['label' => 'Summer Camp', 'url' => '/summer-camp'],
            ['label' => 'Iscrizioni', 'url' => '/summer-camp#iscrizioni'],
        ]);

        // 7. Sociale
        $sociale = MenuItem::create([
            'label' => 'Sociale',
            'url' => '/sociale',
            'location' => 'main',
            'sort_order' => 6,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Sport e Società',
            'motto_subtitle' => 'Progetti sociali, inclusione e sostenibilità',
        ]);
        $this->createChildren($sociale, [
            ['label' => 'Volley4All', 'url' => '/sociale'],
            ['label' => 'Progetti Sociali', 'url' => '/sociale#progetti'],
            ['label' => 'Sostenibilità', 'url' => '/sociale#sostenibilita'],
        ]);

        // 8. Media
        $media = MenuItem::create([
            'label' => 'Media',
            'url' => '/comunicazione',
            'location' => 'main',
            'sort_order' => 7,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Area Stampa',
            'motto_subtitle' => 'News, comunicati, accrediti e materiale media',
        ]);
        $this->createChildren($media, [
            ['label' => 'News', 'url' => '/news'],
            ['label' => 'Accrediti Stampa', 'url' => '/comunicazione'],
            ['label' => 'Cartelle Stampa', 'url' => '/comunicazione#cartelle'],
            ['label' => 'Double Face', 'url' => '/comunicazione#double-face'],
            ['label' => 'Foto Gallery', 'url' => '/gallery'],
        ]);

        // 9. Shop
        $shop = MenuItem::create([
            'label' => 'Shop',
            'url' => '/shop',
            'location' => 'main',
            'sort_order' => 8,
            'is_active' => true,
            'is_highlight' => false,
            'motto_title' => 'Shop Ufficiale',
            'motto_subtitle' => 'Maglie, merchandise e accessori della squadra',
        ]);
        $this->createChildren($shop, [
            ['label' => 'Catalogo', 'url' => '/shop'],
            ['label' => 'Carrello', 'url' => '/shop/cart', 'is_highlight' => true],
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
                'parent_id' => $parent->id,
                'location' => $location,
                'sort_order' => $index,
                'is_active' => true,
                'is_highlight' => $child['is_highlight'] ?? false,
            ]);
        }
    }
}
