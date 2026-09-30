<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Support\CondizioniDiVendita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * L'intestatario del bonifico in produzione era troncato e con «BeneSoc»
 * incollato: la migrazione lo riscrive per esteso, ma solo se è ancora quello.
 */
class IntestatarioDelBonificoMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function scrivi(?string $valore): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'shop.bank_transfer_beneficiary'],
            ['value' => $valore, 'type' => 'text', 'group' => 'general', 'updated_at' => now(), 'created_at' => now()],
        );

        SiteSetting::clearCache();
    }

    private function esegui(): void
    {
        (require database_path('migrations/2026_09_30_100000_intestatario_del_bonifico_per_esteso.php'))->up();
    }

    public function test_il_valore_troncato_diventa_la_ragione_sociale(): void
    {
        // Come in produzione, con uno spazio non separabile per buona misura.
        $this->scrivi("Pallavolo Scandicci Savino Del BeneSoc.\u{00A0} Sport. Dilett. A Resp. Li");
        SiteSetting::get('shop.bank_transfer_beneficiary');

        $this->esegui();

        $this->assertSame(CondizioniDiVendita::RAGIONE_SOCIALE, SiteSetting::get('shop.bank_transfer_beneficiary'));
    }

    public function test_un_intestatario_gia_corretto_non_si_tocca(): void
    {
        $this->scrivi('Pallavolo Scandicci Savino Del Bene SSD a r.l.');

        $this->esegui();

        $this->assertSame('Pallavolo Scandicci Savino Del Bene SSD a r.l.', SiteSetting::get('shop.bank_transfer_beneficiary'));
    }
}
