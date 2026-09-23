<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\ShippingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FasceItaliaEPressKitsTest extends TestCase
{
    use RefreshDatabase;

    private function migrazione(): object
    {
        return require database_path('migrations/2026_09_23_160000_fasce_di_peso_italia_e_press_kits.php');
    }

    private function zonaItalia(array $fasce): ShippingZone
    {
        ShippingZone::query()->delete();

        return ShippingZone::create([
            'name' => ['it' => 'Italia', 'en' => 'Italy'],
            'countries' => ['IT'],
            'flat_rate' => 7.50,
            'weight_rates' => $fasce,
            'free_threshold' => 100,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function le_fasce_dell_italia_si_completano_e_la_tariffa_segue_il_peso(): void
    {
        $zona = $this->zonaItalia([['rate' => '7.50', 'max_weight' => '2']]);

        $this->migrazione()->up();
        $this->migrazione()->up();

        $zona->refresh();
        $this->assertCount(6, $zona->weight_rates);
        $this->assertSame(7.5, $zona->calculateShippingCost(50, 1.5));
        $this->assertSame(9.0, $zona->calculateShippingCost(50, 3));
        $this->assertSame(10.5, $zona->calculateShippingCost(50, 10));
        $this->assertSame(12.5, $zona->calculateShippingCost(50, 15));
        $this->assertSame(14.0, $zona->calculateShippingCost(50, 30));
        $this->assertSame(26.0, $zona->calculateShippingCost(50, 31));
        $this->assertSame(0.0, $zona->calculateShippingCost(120, 31));
    }

    #[Test]
    public function fasce_gia_riscritte_dalla_redazione_non_si_toccano(): void
    {
        $fasce = [['rate' => '8.00', 'max_weight' => '3'], ['rate' => '20.00', 'max_weight' => null]];
        $zona = $this->zonaItalia($fasce);

        $this->migrazione()->up();

        $this->assertSame($fasce, $zona->refresh()->weight_rates);
    }

    #[Test]
    public function la_voce_inglese_delle_cartelle_stampa_diventa_press_kits(): void
    {
        $voce = MenuItem::create([
            'label' => ['it' => 'Cartelle Stampa', 'en' => 'Press Folders'],
            'url' => '/comunicazione/cartelle/',
            'location' => 'main',
            'is_active' => true,
        ]);
        $riscritta = MenuItem::create([
            'label' => ['it' => 'Cartelle Stampa', 'en' => 'Media kits'],
            'url' => '/comunicazione/cartelle',
            'location' => 'footer',
            'is_active' => true,
        ]);

        $this->migrazione()->up();

        $this->assertSame('Press Kits', $voce->refresh()->getTranslation('label', 'en'));
        $this->assertSame('Cartelle Stampa', $voce->getTranslation('label', 'it'));
        $this->assertSame('Media kits', $riscritta->refresh()->getTranslation('label', 'en'));
    }
}
