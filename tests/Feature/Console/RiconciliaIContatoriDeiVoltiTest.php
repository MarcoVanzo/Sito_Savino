<?php

namespace Tests\Feature\Console;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il contatore "Esempi AI" del pannello deve dire quanti volti CompreFace
 * conosce davvero, non quanti ne sono stati caricati nel tempo.
 */
class RiconciliaIContatoriDeiVoltiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.compreface.host' => 'http://compreface.test:8000']);
        config(['services.compreface.key' => 'chiave-di-prova']);
    }

    private function compreFaceConosce(array $volti, int $pagine = 1): void
    {
        Http::fake(function ($request) use ($volti, $pagine) {
            $pagina = (int) ($request->data()['page'] ?? 0);

            return Http::response([
                'faces' => $volti[$pagina] ?? [],
                'page_number' => $pagina,
                'total_pages' => $pagine,
            ]);
        });
    }

    #[Test]
    public function i_contatori_seguono_compreface_in_entrambe_le_direzioni(): void
    {
        $gonfiata = Player::factory()->create(['ai_face_examples' => 7]);
        $azzerata = Player::factory()->create(['ai_face_examples' => 0]);
        $sparita = Player::factory()->create(['ai_face_examples' => 4]);

        $this->compreFaceConosce([[
            ['image_id' => 'a', 'subject' => 'player_'.$gonfiata->id],
            ['image_id' => 'b', 'subject' => 'player_'.$azzerata->id],
            ['image_id' => 'c', 'subject' => 'player_'.$azzerata->id],
            ['image_id' => 'd', 'subject' => 'player_'.$azzerata->id],
            ['image_id' => 'e', 'subject' => 'staff_16'],
        ]]);

        $this->artisan('volti:riconcilia-contatori')
            ->expectsOutputToContain('Contatori aggiornati: 3. Atlete senza esempi: 1. Membri dello staff con esempi: 1.')
            ->assertSuccessful();

        $this->assertSame(1, $gonfiata->fresh()->ai_face_examples);
        $this->assertSame(3, $azzerata->fresh()->ai_face_examples);
        $this->assertSame(0, $sparita->fresh()->ai_face_examples);
    }

    #[Test]
    public function l_elenco_si_legge_su_tutte_le_pagine(): void
    {
        $atleta = Player::factory()->create(['ai_face_examples' => 0]);

        $this->compreFaceConosce([
            [['image_id' => 'a', 'subject' => 'player_'.$atleta->id]],
            [['image_id' => 'b', 'subject' => 'player_'.$atleta->id]],
        ], pagine: 2);

        $this->artisan('volti:riconcilia-contatori')->assertSuccessful();

        $this->assertSame(2, $atleta->fresh()->ai_face_examples);
        Http::assertSentCount(2);
    }

    #[Test]
    public function senza_compreface_i_contatori_restano_come_sono(): void
    {
        $atleta = Player::factory()->create(['ai_face_examples' => 5]);
        Http::fake(fn () => throw new ConnectionException('cURL error 28'));

        $this->artisan('volti:riconcilia-contatori')
            ->expectsOutputToContain('contatori lasciati invariati')
            ->assertFailed();

        $this->assertSame(5, $atleta->fresh()->ai_face_examples);
    }

    #[Test]
    public function un_errore_del_server_non_azzera_nulla(): void
    {
        $atleta = Player::factory()->create(['ai_face_examples' => 5]);
        Http::fake(['*' => Http::response(['message' => 'Recognition service not found'], 404)]);

        $this->artisan('volti:riconcilia-contatori')->assertFailed();

        $this->assertSame(5, $atleta->fresh()->ai_face_examples);
    }
}
