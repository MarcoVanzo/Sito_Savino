<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Complete English translations for ALL menu items.
     *
     * The previous migration (2026_07_04_225354) only covered ~15 items.
     * This one maps every single item by ID for reliability.
     */
    public function up(): void
    {
        $translations = [
            // ===== MAIN MENU =====
            // Top-level
            1 => 'Season',
            9 => 'Club',
            15 => 'Ticketing',
            20 => 'Sponsors',
            25 => 'SDB Youth',
            31 => 'Summer Camp',
            34 => 'Community',
            40 => 'Communications',
            45 => 'Official Shop',

            // Stagione children (parent_id=1)
            2 => 'Serie A1',
            3 => 'Official Photos',
            4 => 'Standings & Results',
            5 => 'CEV Champions League',
            6 => 'Coppa Italia & Playoffs',
            7 => 'News & Press Releases',
            8 => 'Photo Gallery',

            // Società children (parent_id=9)
            10 => 'Organisation Chart',
            11 => 'History',
            12 => 'Safeguarding',
            13 => 'Contacts',
            14 => 'Arena & Directions',

            // Ticketing children (parent_id=15)
            16 => 'Ticketing',
            17 => 'Season Tickets',
            18 => 'Accessibility & Info',
            19 => 'Partnerships',

            // Sponsor children (parent_id=20)
            21 => 'Our Sponsors',
            22 => 'Become a Sponsor',
            23 => 'Title Sponsor (SDB)',
            24 => 'Hospitality',

            // SDB Youth children (parent_id=25)
            26 => 'Serie B1 / U19',
            27 => 'U17 & U15',
            28 => 'Youth Academy',
            29 => 'Talent Day & Scouting',
            30 => 'Affiliations Programme',

            // Summer Camp children (parent_id=31)
            32 => 'All Info',
            33 => 'Enrolment (Experience)',

            // Sociale children (parent_id=34)
            35 => 'Social Projects',
            36 => 'Volley 4 All',
            37 => 'Sustainability Report',
            38 => 'Schools Programme',
            39 => 'Charity Auctions',

            // Comunicazione children (parent_id=40)
            41 => 'Press Accreditation',
            42 => 'Press Kits',
            43 => 'Magazine',
            44 => 'Double Face',

            // Shop Ufficiale children (parent_id=45)
            46 => 'Match Kit',
            47 => 'Clothing & Accessories',
            48 => 'Auctions & Outlet',
            49 => 'Size Guide & Contacts',

            // ===== FOOTER MENU =====
            50 => 'Season',
            51 => 'A1 Roster',
            52 => 'Results',
            53 => 'Gallery',
            54 => 'Coaching Staff',
            55 => 'The Club',
            56 => 'The Club',
            57 => 'Youth Academy',
            58 => 'Sponsors',
            59 => 'News',
            60 => 'Services',
            61 => 'Ticketing',
            62 => 'Official Shop',
            63 => 'Contacts',
            64 => 'Communications',
        ];

        foreach ($translations as $id => $enLabel) {
            $item = MenuItem::find($id);
            if ($item) {
                $item->setTranslation('label', 'en', $enLabel);
                $item->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove English translations, leaving only Italian
        foreach (MenuItem::all() as $item) {
            $item->forgetTranslation('label', 'en');
            $item->save();
        }
    }
};
