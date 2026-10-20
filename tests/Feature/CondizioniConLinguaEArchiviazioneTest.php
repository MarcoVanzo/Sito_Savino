<?php

namespace Tests\Feature;

use App\Support\CondizioniDiVendita;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * La revisione del 20 ottobre 2026 delle Condizioni di vendita: lingua del
 * contratto, archiviazione, accessibilità. La migrazione deve portare il testo
 * pubblicato il 2 ottobre esattamente a quello del file dati, e lasciare stare
 * una pagina riscritta dalla redazione.
 */
class CondizioniConLinguaEArchiviazioneTest extends TestCase
{
    use RefreshDatabase;

    private function migrazione(): Migration
    {
        return require database_path('migrations/2026_10_20_110000_condizioni_lingua_archiviazione_e_accessibilita.php');
    }

    /**
     * Il testo del 2 ottobre: quello di oggi con le correzioni tolte.
     *
     * @return array<string, string>
     */
    private function pubblicatoIlDueOttobre(): array
    {
        $correzioni = (new ReflectionClassConstant($this->migrazione(), 'CORREZIONI'))->getValue();

        return array_map(function (string $testo) use ($correzioni) {
            foreach ($correzioni as [$vecchio, $nuovo]) {
                $testo = str_replace($nuovo, $vecchio, $testo);
            }

            return $testo;
        }, CondizioniDiVendita::contenuto(CondizioniDiVendita::SLUG_CONDIZIONI));
    }

    private function pubblica(array $contenuto): void
    {
        DB::table('pages')->where('slug', CondizioniDiVendita::SLUG_CONDIZIONI)->delete();
        DB::table('pages')->insert([
            'slug' => CondizioniDiVendita::SLUG_CONDIZIONI,
            'title' => json_encode(['it' => 'Condizioni di vendita', 'en' => 'Terms of sale']),
            'template' => 'Public/ContentPage',
            'status' => 'publish',
            'content' => json_encode($contenuto, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function pubblicato(): array
    {
        return json_decode((string) DB::table('pages')->where('slug', CondizioniDiVendita::SLUG_CONDIZIONI)->value('content'), true);
    }

    #[Test]
    public function il_testo_del_due_ottobre_diventa_quello_del_file_dati_anche_rieseguendo(): void
    {
        $vecchio = $this->pubblicatoIlDueOttobre();
        $this->assertStringContainsString('Sono aggiornate al 2 ottobre 2026.', $vecchio['it']);
        $this->assertStringNotContainsString('11. Accessibilità', $vecchio['it']);

        $this->pubblica($vecchio);

        $this->migrazione()->up();
        $this->migrazione()->up();

        $this->assertSame(CondizioniDiVendita::contenuto(CondizioniDiVendita::SLUG_CONDIZIONI), $this->pubblicato());
    }

    #[Test]
    public function il_testo_nuovo_dice_lingue_archiviazione_e_accessibilita_in_tutte_e_due_le_lingue(): void
    {
        $testo = CondizioniDiVendita::contenuto(CondizioniDiVendita::SLUG_CONDIZIONI);

        $this->assertStringContainsString('in italiano o in inglese', $testo['it']);
        $this->assertStringContainsString('Il contratto viene archiviato', $testo['it']);
        $this->assertStringContainsString('href="/dichiarazione-di-accessibilita"', $testo['it']);
        $this->assertStringContainsString('in Italian or in English', $testo['en']);
        $this->assertStringContainsString('The contract is archived', $testo['en']);
        $this->assertStringContainsString('href="/en/dichiarazione-di-accessibilita"', $testo['en']);
    }

    #[Test]
    public function non_tocca_le_condizioni_scritte_dalla_redazione(): void
    {
        $this->pubblica(['it' => '<p>Condizioni della redazione.</p>', 'en' => '<p>Editorial terms.</p>']);

        $this->migrazione()->up();

        $this->assertSame(['it' => '<p>Condizioni della redazione.</p>', 'en' => '<p>Editorial terms.</p>'], $this->pubblicato());
    }
}
