<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MenuItem;

class CorporateGovernanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Controlla se esiste già
        if (MenuItem::where('location', 'footer')->where('url', '#')->where('label->it', 'Corporate Governance')->exists()) {
            return;
        }

        $parent = MenuItem::create([
            'label' => ['it' => 'Corporate Governance', 'en' => 'Corporate Governance'],
            'url' => '#',
            'location' => 'footer',
            'sort_order' => 3, // Dopo Servizi che ha 2
            'is_active' => true,
        ]);

        $links = [
            ['label' => 'Safeguarding', 'url' => '/societa/safeguarding'],
            ['label' => 'Protocollo Razzismo', 'url' => '/protocollo-razzismo'],
            ['label' => 'Protocollo Bullismo', 'url' => '/protocollo-bullismo'],
            ['label' => 'Codice Tutela Minori', 'url' => '/codice-tutela-minori'],
            ['label' => 'Modello Organizzativo', 'url' => '/modello-organizzativo'],
        ];

        foreach ($links as $index => $link) {
            MenuItem::create([
                'label' => ['it' => $link['label'], 'en' => $link['label']],
                'url' => $link['url'],
                'location' => 'footer',
                'parent_id' => $parent->id,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }
}
