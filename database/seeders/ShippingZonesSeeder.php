<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Seed delle zone di spedizione con tariffe reali.
 *
 * Fonti tariffarie (aggiornamento luglio 2026):
 * - Italia: Poste Delivery Standard (fascia 0-3 kg = €10,30)
 * - Europa: BRT/DPD network (zone 1-4, fascia 0-2 kg)
 * - Extra-EU: Poste Delivery International Standard (zona 1+, fascia 0-1 kg da €25,80)
 *
 * Le tariffe flat sono calibrate sulla fascia 0-3 kg, tipica per
 * merchandising sportivo (maglie, sciarpe, accessori Savino Del Bene Volley).
 *
 * Idempotente: svuota e ricrea per aggiornare tariffe e paesi.
 */
class ShippingZonesSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            // ─────────────────────────────────────────────────────
            // ITALIA — Poste Delivery Standard / BRT nazionale
            // Poste: 0-3 kg = €10,30 | BRT: ~€7,50-10,00
            // Tariffa media: €7,90 (negoziabile con contratto BRT)
            // Spedizione gratuita sopra €50
            // ─────────────────────────────────────────────────────
            [
                'name' => [
                    'it' => 'Italia',
                    'en' => 'Italy',
                ],
                'countries' => ['IT'],
                'flat_rate' => 7.90,
                'free_threshold' => 50.00,
                'estimated_days_min' => 2,
                'estimated_days_max' => 4,
                'is_active' => true,
                'sort_order' => 1,
            ],

            // ─────────────────────────────────────────────────────
            // EUROPA ZONA 1 — BRT/DPD Zona 1
            // Paesi vicini: FR, NL, AT, HR, HU, DE, CH, SI
            // BRT 0-2 kg = €23,50
            // ─────────────────────────────────────────────────────
            [
                'name' => [
                    'it' => 'Europa Zona 1 (Francia, Germania, Austria, Svizzera)',
                    'en' => 'Europe Zone 1 (France, Germany, Austria, Switzerland)',
                ],
                'countries' => ['FR', 'NL', 'AT', 'HR', 'HU', 'DE', 'CH', 'SI'],
                'flat_rate' => 14.90,
                'free_threshold' => 100.00,
                'estimated_days_min' => 3,
                'estimated_days_max' => 5,
                'is_active' => true,
                'sort_order' => 2,
            ],

            // ─────────────────────────────────────────────────────
            // EUROPA ZONA 2 — BRT/DPD Zona 2
            // ES, BG, BE, PL, CZ, LU
            // BRT 0-2 kg = €24,50
            // ─────────────────────────────────────────────────────
            [
                'name' => [
                    'it' => 'Europa Zona 2 (Spagna, Belgio, Polonia, Rep. Ceca)',
                    'en' => 'Europe Zone 2 (Spain, Belgium, Poland, Czech Rep.)',
                ],
                'countries' => ['ES', 'BG', 'BE', 'PL', 'CZ', 'LU'],
                'flat_rate' => 16.90,
                'free_threshold' => 100.00,
                'estimated_days_min' => 4,
                'estimated_days_max' => 7,
                'is_active' => true,
                'sort_order' => 3,
            ],

            // ─────────────────────────────────────────────────────
            // EUROPA ZONA 3 — BRT/DPD Zona 3
            // DK, PT, GR, SK, RO
            // BRT 0-2 kg = €28,16
            // ─────────────────────────────────────────────────────
            [
                'name' => [
                    'it' => 'Europa Zona 3 (Danimarca, Portogallo, Grecia, Romania)',
                    'en' => 'Europe Zone 3 (Denmark, Portugal, Greece, Romania)',
                ],
                'countries' => ['DK', 'PT', 'GR', 'SK', 'RO'],
                'flat_rate' => 19.90,
                'free_threshold' => null,
                'estimated_days_min' => 5,
                'estimated_days_max' => 8,
                'is_active' => true,
                'sort_order' => 4,
            ],

            // ─────────────────────────────────────────────────────
            // EUROPA ZONA 4 — BRT/DPD Zona 4
            // FI, EE, LV, LT, SE, IE, GB, NO, IS, MT, CY
            // BRT 0-2 kg = €30,90
            // ─────────────────────────────────────────────────────
            [
                'name' => [
                    'it' => 'Europa Zona 4 (Scandinavia, Baltici, UK, Irlanda)',
                    'en' => 'Europe Zone 4 (Scandinavia, Baltics, UK, Ireland)',
                ],
                'countries' => ['FI', 'EE', 'LV', 'LT', 'SE', 'IE', 'GB', 'NO', 'IS', 'MT', 'CY'],
                'flat_rate' => 22.90,
                'free_threshold' => null,
                'estimated_days_min' => 5,
                'estimated_days_max' => 10,
                'is_active' => true,
                'sort_order' => 5,
            ],

            // ─────────────────────────────────────────────────────
            // EXTRA-EU — Poste Delivery International Standard
            // Zona 1+ fino a 1 kg da €25,80
            // Tariffa e-commerce: €29,90 (media pesata su destinazioni comuni)
            // ─────────────────────────────────────────────────────
            [
                'name' => [
                    'it' => 'Resto del Mondo',
                    'en' => 'Rest of the World',
                ],
                'countries' => ['*'], // Wildcard: qualsiasi paese non coperto dalle zone precedenti
                'flat_rate' => 29.90,
                'free_threshold' => null,
                'estimated_days_min' => 10,
                'estimated_days_max' => 20,
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];

        // Svuota e ricrea per aggiornare tariffe e paesi
        ShippingZone::query()->delete();

        foreach ($zones as $zoneData) {
            $zone = new ShippingZone;
            // HasTranslations gestisce automaticamente l'array name -> JSON
            $zone->setTranslations('name', $zoneData['name']);
            $zone->countries = $zoneData['countries'];
            $zone->flat_rate = $zoneData['flat_rate'];
            $zone->free_threshold = $zoneData['free_threshold'];
            $zone->estimated_days_min = $zoneData['estimated_days_min'];
            $zone->estimated_days_max = $zoneData['estimated_days_max'];
            $zone->is_active = $zoneData['is_active'];
            $zone->sort_order = $zoneData['sort_order'];
            $zone->save();
        }

        $this->command->info('✅ Zone di spedizione aggiornate con tariffe reali (Poste Italiane + BRT/DPD).');
    }
}
