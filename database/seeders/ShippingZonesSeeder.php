<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Seed delle zone di spedizione predefinite.
 *
 * Crea le 3 zone base: Italia, Europa, Extra-EU.
 * Idempotente: non ricrea le zone se esistono già.
 */
class ShippingZonesSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'Italia',
                'countries' => ['IT'],
                'flat_rate' => 5.90,
                'free_threshold' => 50.00,
                'estimated_days_min' => 2,
                'estimated_days_max' => 4,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Europa',
                'countries' => [
                    'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
                    'DE', 'GR', 'HU', 'IE', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL',
                    'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
                    // Paesi extra-EU ma geograficamente europei
                    'CH', 'GB', 'NO', 'IS',
                ],
                'flat_rate' => 12.90,
                'free_threshold' => null,
                'estimated_days_min' => 5,
                'estimated_days_max' => 10,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Extra-EU',
                'countries' => ['*'], // Wildcard: qualsiasi paese non coperto dalle zone precedenti
                'flat_rate' => 24.90,
                'free_threshold' => null,
                'estimated_days_min' => 10,
                'estimated_days_max' => 20,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($zones as $zone) {
            ShippingZone::firstOrCreate(
                ['name' => $zone['name']],
                $zone,
            );
        }

        $this->command->info('✅ Zone di spedizione predefinite create.');
    }
}
