<?php

namespace Tests\Feature\Shop;

use App\Models\Page;
use App\Models\RichiestaDiRecesso;
use App\Support\TestiDelleInformative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le dichiarazioni di recesso si tengono dodici mesi dall'invio, ed è quello
 * che l'informativa dichiara. Una conservazione scritta senza un comando che
 * la applichi è una frase falsa (§21 del CLAUDE.md).
 */
class ConservazioneDeiRecessiTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_prune_toglie_le_dichiarazioni_oltre_i_dodici_mesi(): void
    {
        $vecchia = $this->richiesta(now()->subMonths(12)->subDay());
        $recente = $this->richiesta(now()->subMonths(11));

        $this->artisan('model:prune', ['--model' => [RichiestaDiRecesso::class]])->assertSuccessful();

        $this->assertModelMissing($vecchia);
        $this->assertModelExists($recente);
    }

    public function test_l_informativa_dichiara_i_dodici_mesi_in_entrambe_le_lingue(): void
    {
        $testi = TestiDelleInformative::contenuto('privacy-policy');

        $this->assertStringContainsString("Dichiarazioni di recesso inviate dal sito: 12 mesi dall'invio", $testi['it']);
        $this->assertStringContainsString('Withdrawal notices sent from the site: 12 months from sending', $testi['en']);
    }

    public function test_la_revisione_riscrive_solo_la_pagina_non_toccata_dalla_redazione(): void
    {
        $firma = "ordini, offerte): servono a concludere e gestire l'acquisto e a rispettare gli obblighi fiscali.";
        $pagina = Page::where('slug', 'privacy-policy')->first() ?? Page::factory()->create(['slug' => 'privacy-policy']);
        $pagina->setTranslations('content', ['it' => "<p>Testo di prima: {$firma}</p>", 'en' => '<p>old</p>'])->save();

        TestiDelleInformative::riscriviDoveNonToccata();

        $this->assertStringContainsString('12 mesi dall', $pagina->fresh()->getTranslation('content', 'it'));

        $pagina->setTranslations('content', ['it' => '<p>Riscritta dalla redazione</p>'])->save();
        TestiDelleInformative::riscriviDoveNonToccata();

        $this->assertSame('<p>Riscritta dalla redazione</p>', $pagina->fresh()->getTranslation('content', 'it'));
    }

    private function richiesta($inviataIl): RichiestaDiRecesso
    {
        return RichiestaDiRecesso::create([
            'numero_ordine' => 'SDB-1',
            'nome' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'lingua' => 'it',
            'inviata_il' => $inviataIl,
        ]);
    }
}
