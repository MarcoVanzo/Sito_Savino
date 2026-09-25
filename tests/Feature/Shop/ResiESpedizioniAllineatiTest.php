<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La migrazione del 26/09/2026 corregge due frasi delle pagine pratiche dello
 * shop solo dove sono ancora quelle pubblicate il giorno prima.
 */
class ResiESpedizioniAllineatiTest extends TestCase
{
    use RefreshDatabase;

    private function migrazione(): object
    {
        return require database_path('migrations/2026_09_26_120000_resi_e_spedizioni_allineati_alle_condizioni.php');
    }

    #[Test]
    public function toglie_l_esclusione_dei_sigillati_dalla_pagina_pubblicata(): void
    {
        DB::table('pages')->where('slug', 'resi-e-rimborsi')->delete();
        DB::table('pages')->insert([
            'title' => json_encode(['it' => 'Resi', 'en' => 'Returns']),
            'slug' => 'resi-e-rimborsi',
            'template' => 'Public/ContentPage',
            'content' => json_encode([
                'it' => '<p>Non si possono restituire per recesso i prodotti personalizzati su tua richiesta (per esempio con nome e numero a tua scelta) e i prodotti sigillati per motivi igienici aperti dopo la consegna. La scheda del prodotto lo indica prima dell\'acquisto.</p>',
                'en' => '<p>Scritto a mano dalla redazione.</p>',
            ], JSON_UNESCAPED_UNICODE),
            'status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migrazione()->up();

        $contenuto = json_decode(DB::table('pages')->where('slug', 'resi-e-rimborsi')->value('content'), true);
        $this->assertStringNotContainsString('sigillati', $contenuto['it']);
        $this->assertStringContainsString('solo quegli articoli sono esclusi', $contenuto['it']);
        $this->assertSame('<p>Scritto a mano dalla redazione.</p>', $contenuto['en']);
    }

    #[Test]
    public function i_testi_di_partenza_non_escludono_i_sigillati(): void
    {
        $pagine = require database_path('data/condizioni_shop.php');

        foreach ($pagine['resi-e-rimborsi']['contenuto'] as $testo) {
            $this->assertStringNotContainsString('sigillati', $testo);
            $this->assertStringNotContainsString('sealed', $testo);
        }
    }
}
