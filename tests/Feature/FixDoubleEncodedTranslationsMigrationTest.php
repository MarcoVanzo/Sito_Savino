<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La correzione dei campi codificati due volte era un comando artisan eseguito
 * a ogni avvio del container. Diventata migrazione, gira una volta sola: questi
 * test verificano che faccia lo stesso lavoro e — soprattutto — che non tocchi
 * ciò che è già a posto, perché a differenza di prima non c'è un secondo giro a
 * rimediare.
 */
class FixDoubleEncodedTranslationsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        (require database_path('migrations/2026_07_31_120000_fix_double_encoded_translations.php'))->up();
    }

    private function insertPost(string $rawTitle): int
    {
        return DB::table('posts')->insertGetId([
            'title' => $rawTitle,
            'slug' => 'post-'.uniqid(),
            'content' => json_encode(['it' => 'contenuto']),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function sfila_la_codifica_in_eccesso(): void
    {
        $id = $this->insertPost(json_encode(['it' => json_encode(['it' => 'Vittoria al tie-break'])]));

        $this->runMigration();

        $title = json_decode((string) DB::table('posts')->where('id', $id)->value('title'), true);
        $this->assertSame('Vittoria al tie-break', $title['it']);
    }

    #[Test]
    public function lascia_intatto_un_campo_gia_corretto(): void
    {
        $id = $this->insertPost(json_encode(['it' => 'Titolo sano', 'en' => 'Healthy title']));

        $this->runMigration();

        $title = json_decode((string) DB::table('posts')->where('id', $id)->value('title'), true);
        $this->assertSame('Titolo sano', $title['it']);
        $this->assertSame('Healthy title', $title['en']);
    }

    /**
     * Il caso che rende pericolosa una correzione automatica: un articolo che
     * cita un frammento JSON è testo legittimo, non una doppia codifica. Si
     * sfila solo quando il valore interno ripete la STESSA lingua.
     */
    #[Test]
    public function non_tocca_un_testo_che_e_per_caso_json_valido(): void
    {
        $jsonNelTesto = '{"squadra":"Savino","punti":42}';
        $id = $this->insertPost(json_encode(['it' => $jsonNelTesto]));

        $this->runMigration();

        $title = json_decode((string) DB::table('posts')->where('id', $id)->value('title'), true);
        $this->assertSame($jsonNelTesto, $title['it']);
    }

    #[Test]
    public function riporta_a_testo_semplice_i_campi_non_translatable(): void
    {
        // `categories.name` non usa HasTranslations, ma l'import ci ha scritto
        // dentro un JSON per lingua: il CMS mostrava «{"it":"Prima squadra"}».
        $id = DB::table('categories')->insertGetId([
            'name' => json_encode(['it' => 'Prima squadra']),
            'slug' => 'prima-squadra-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runMigration();

        $this->assertSame('Prima squadra', DB::table('categories')->where('id', $id)->value('name'));
    }

    #[Test]
    public function e_idempotente(): void
    {
        $id = $this->insertPost(json_encode(['it' => json_encode(['it' => 'Doppio'])]));

        $this->runMigration();
        $this->runMigration();

        $title = json_decode((string) DB::table('posts')->where('id', $id)->value('title'), true);
        $this->assertSame('Doppio', $title['it']);
    }
}
